<?php

declare(strict_types=1);

use App\Models\User;
use Application\Booking\BookSpace;
use Application\Booking\BookSpaceHandler;
use Application\Booking\CancelBooking;
use Application\Booking\CancelBookingHandler;
use Application\Event\Outbox;
use Application\Payment\PayForBooking;
use Application\Payment\PayForBookingHandler;
use Application\Transaction;
use Domain\Booking\CustomerId;
use Domain\Booking\Event\BookingRequested;
use Domain\Cancellation\CancellationPolicyType;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Infrastructure\Notification\BookingConfirmedMail;
use Infrastructure\Notification\WebhookSignature;
use Tests\Support\Builders;

beforeEach(function (): void {
    config()->set('webhooks.endpoints', ['https://partner.example/hooks/bookly']);
    config()->set('webhooks.secret', 'whsec_test');
    Mail::fake();

    $this->user = User::factory()->create(['email' => 'ana@example.com', 'name' => 'Ana']);
    $this->space = Builders::space(policy: CancellationPolicyType::Flexible);
    app(SpaceRepository::class)->save($this->space);

    // Relative to the real clock, so the booking rules accept it.
    $start = (new DateTimeImmutable('+10 days', new DateTimeZone('UTC')))->setTime(14, 0);
    $this->slot = [$start->format(DATE_ATOM), $start->modify('+1 hour')->format(DATE_ATOM)];
});

it('delivers the whole booking lifecycle to audit, e-mail and webhooks, in order', function (): void {
    Http::fake(['partner.example/*' => Http::response(['ok' => true])]);
    $booking = app(BookSpaceHandler::class)->handle(new BookSpace($this->space->id->value, $this->user->id, ...$this->slot))->bookingId;
    app(PayForBookingHandler::class)->handle(new PayForBooking($booking->value, $this->user->id, 'pm_card_visa'));
    app(CancelBookingHandler::class)->handle(new CancelBooking($booking->value, $this->user->id));

    expect(DB::table('outbox')->whereNull('published_at')->count())->toBe(3);

    $this->artisan('outbox:relay', ['--until-empty' => true])->assertSuccessful();

    expect(DB::table('outbox')->whereNull('published_at')->count())->toBe(0)
        ->and(DB::table('audit_log')->orderBy('occurred_at')->pluck('event_name')->all())
        ->toBe(['booking.requested', 'booking.confirmed', 'booking.cancelled']);

    Mail::assertSent(BookingConfirmedMail::class, 1);
    Mail::assertSent(BookingConfirmedMail::class, fn (BookingConfirmedMail $mail): bool => $mail->hasTo('ana@example.com')
        && $mail->booking['booking_id'] === $booking->value);

    $webhooks = Http::recorded()->map(fn (array $pair): Request => $pair[0])->values();
    expect($webhooks)->toHaveCount(3)
        ->and($webhooks->map(fn (Request $r): mixed => $r->data()['type'])->all())
        ->toBe(['booking.requested', 'booking.confirmed', 'booking.cancelled']);

    foreach ($webhooks as $request) {
        expect(WebhookSignature::verify($request->body(), $request->header('Bookly-Signature')[0], 'whsec_test', time()))->toBeTrue()
            ->and($request->header('Bookly-Event-Id')[0])->toBe($request->data()['id']);
    }
});

it('retries a failing webhook without sending the e-mail again', function (): void {
    Http::fake(['partner.example/*' => Http::sequence()->push('down', 503)->whenEmpty(Http::response(['ok' => true]))]);
    $booking = app(BookSpaceHandler::class)->handle(new BookSpace($this->space->id->value, $this->user->id, ...$this->slot))->bookingId;
    app(PayForBookingHandler::class)->handle(new PayForBooking($booking->value, $this->user->id, 'pm_card_visa'));

    $this->artisan('outbox:relay', ['--until-empty' => true]);
    expect(DB::table('outbox')->whereNull('published_at')->count())->toBe(2)
        ->and(DB::table('outbox')->where('attempts', 1)->value('last_error'))->toContain('503');

    DB::table('outbox')->update(['available_at' => now()->subSecond()]); // skip the backoff
    $this->artisan('outbox:relay', ['--until-empty' => true]);

    expect(DB::table('outbox')->whereNull('published_at')->count())->toBe(0);
    Mail::assertSent(BookingConfirmedMail::class, 1);
});

it('stores events atomically with the change that caused them', function (): void {
    $event = BookingRequested::of(Builders::booking(SpaceId::generate(), new CustomerId(1)));

    expect(fn () => app(Transaction::class)->run(function () use ($event): void {
        app(Outbox::class)->append(new DateTimeImmutable, $event);

        throw new RuntimeException('the booking could not be saved');
    }))->toThrow(RuntimeException::class);

    expect(DB::table('outbox')->count())->toBe(0);
});
