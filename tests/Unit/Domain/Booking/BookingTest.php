<?php

declare(strict_types=1);

use Domain\Booking\Booking;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingAlreadyStarted;
use Domain\Booking\Exception\InvalidBookingTransition;
use Domain\Cancellation\CancellationPolicyType;
use Domain\Shared\Event\DomainEvent;
use Domain\Space\SpaceId;
use Tests\Support\Builders;

beforeEach(function (): void {
    $this->space = SpaceId::generate();
    $this->customer = new CustomerId(7);
});

it('starts as pending and holds its slot', function (): void {
    $booking = Builders::booking($this->space, $this->customer);

    expect($booking->status())->toBe(BookingStatus::Pending)
        ->and($booking->holdsSlot())->toBeTrue()
        ->and($booking->cancellation())->toBeNull();
});

it('can be confirmed and then cancelled', function (): void {
    $booking = Builders::booking($this->space, $this->customer);

    $booking->confirm();
    expect($booking->status())->toBe(BookingStatus::Confirmed);

    Builders::cancel($booking);
    expect($booking->status())->toBe(BookingStatus::Cancelled)
        ->and($booking->holdsSlot())->toBeFalse();
});

it('refunds a confirmed booking according to the policy', function (string $cancelledAt, int $refund): void {
    // Slot starts 2026-11-02T10:00Z and costs BRL 50.00
    $booking = Builders::booking($this->space, $this->customer);
    $booking->confirm();

    $returned = $booking->cancel(new DateTimeImmutable($cancelledAt), CancellationPolicyType::Moderate->policy());

    expect($returned->amount)->toBe($refund)
        ->and($booking->cancellation()?->refund->amount)->toBe($refund)
        ->and($booking->cancellation()?->cancelledAt->getTimestamp())->toBe((new DateTimeImmutable($cancelledAt))->getTimestamp());
})->with([
    '3 days before' => ['2026-10-30T10:00:00Z', 5_000],
    'exactly 48h before' => ['2026-10-31T10:00:00Z', 5_000],
    '30h before' => ['2026-11-01T04:00:00Z', 2_500],
    '2h before' => ['2026-11-02T08:00:00Z', 0],
]);

it('refunds nothing for a pending booking, which was never paid', function (): void {
    $booking = Builders::booking($this->space, $this->customer);

    $refund = $booking->cancel(new DateTimeImmutable('2026-10-01T00:00:00Z'), CancellationPolicyType::Flexible->policy());

    expect($refund->isZero())->toBeTrue()
        ->and($booking->status())->toBe(BookingStatus::Cancelled);
});

it('cannot be cancelled once the slot has started', function (string $at): void {
    $booking = Builders::booking($this->space, $this->customer);
    $booking->confirm();

    $booking->cancel(new DateTimeImmutable($at), CancellationPolicyType::Flexible->policy());
})->with([
    'at the start' => ['2026-11-02T10:00:00Z'],
    'midway' => ['2026-11-02T10:30:00Z'],
])->throws(BookingAlreadyStarted::class);

it('rejects invalid transitions', function (Closure $act): void {
    $booking = Builders::booking($this->space, $this->customer);

    $act($booking);
})->with([
    'confirm twice' => [function (Booking $b): void {
        $b->confirm();
        $b->confirm();
    }],
    'cancel twice' => [function (Booking $b): void {
        Builders::cancel($b);
        Builders::cancel($b);
    }],
    'confirm after cancel' => [function (Booking $b): void {
        Builders::cancel($b);
        $b->confirm();
    }],
])->throws(InvalidBookingTransition::class);

it('conflicts only with active, overlapping bookings of the same space', function (): void {
    $booking = Builders::booking($this->space, $this->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
    $overlapping = Builders::booking($this->space, $this->customer, '2026-11-02T10:30:00Z', '2026-11-02T11:30:00Z');
    $adjacent = Builders::booking($this->space, $this->customer, '2026-11-02T11:00:00Z', '2026-11-02T12:00:00Z');
    $elsewhere = Builders::booking(SpaceId::generate(), $this->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
    $cancelled = Builders::cancel(Builders::booking($this->space, $this->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z'));

    expect($booking->conflictsWith($overlapping))->toBeTrue()
        ->and($booking->conflictsWith($adjacent))->toBeFalse()
        ->and($booking->conflictsWith($elsewhere))->toBeFalse()
        ->and($booking->conflictsWith($cancelled))->toBeFalse()
        ->and($booking->conflictsWith($booking))->toBeFalse();
});

it('keeps the slot-holding rule in sync with the database constraint', function (): void {
    expect(array_map(static fn (BookingStatus $s): bool => $s->holdsSlot(), BookingStatus::cases()))
        ->toBe([true, true, false, false]);
});

it('records what happened to it as domain events', function (): void {
    $booking = Builders::booking($this->space, $this->customer);
    $booking->confirm();
    Builders::cancel($booking);

    $events = $booking->releaseEvents();

    expect(array_map(static fn (DomainEvent $e): string => $e::name(), $events))->toBe(['booking.requested', 'booking.confirmed', 'booking.cancelled'])
        ->and($events[2]->payload())->toMatchArray([
            'booking_id' => $booking->id->value,
            'start' => $booking->slot->start->format(DATE_ATOM),
            'end' => $booking->slot->end->format(DATE_ATOM),
            'refund_amount' => 5_000,
        ])
        ->and($booking->releaseEvents())->toBe([]);
});

it('records nothing when rebuilt from storage', function (): void {
    $booking = Builders::booking($this->space, $this->customer);
    $copy = Booking::reconstitute($booking->id, $booking->spaceId, $booking->customerId, $booking->slot, $booking->price, $booking->holdUntil, BookingStatus::Pending);

    expect($copy->releaseEvents())->toBe([]);
});

it('publishes every field of its events, which webhooks and the realtime service rely on', function (): void {
    $booking = Builders::booking($this->space, $this->customer);
    $booking->confirm();
    Builders::cancel($booking);
    $common = [
        'booking_id' => $booking->id->value,
        'space_id' => $this->space->value,
        'customer_id' => $this->customer->value,
        'start' => $booking->slot->start->format(DATE_ATOM),
        'end' => $booking->slot->end->format(DATE_ATOM),
    ];

    [$requested, $confirmed, $cancelled] = array_map(static fn (DomainEvent $e): array => $e->payload(), $booking->releaseEvents());

    expect($requested)->toBe([...$common, 'price_amount' => 5_000, 'currency' => 'BRL'])
        ->and($confirmed)->toBe([...$common, 'paid_amount' => 5_000, 'currency' => 'BRL'])
        ->and($cancelled)->toBe([...$common, 'refund_amount' => 5_000, 'currency' => 'BRL']);
});

describe('payment hold', function (): void {
    beforeEach(function (): void {
        $this->booking = Builders::booking($this->space, $this->customer, holdUntil: '2026-11-01T09:15:00-03:00');
        $this->booking->releaseEvents();
    });

    it('keeps its deadline in UTC', function (): void {
        expect($this->booking->holdUntil->format(DATE_ATOM))->toBe('2026-11-01T12:15:00+00:00');
    });

    it('ends exactly at the deadline, and only for an unpaid booking', function (): void {
        expect($this->booking->holdHasEnded(new DateTimeImmutable('2026-11-01T12:14:59Z')))->toBeFalse()
            ->and($this->booking->holdHasEnded(new DateTimeImmutable('2026-11-01T12:15:00Z')))->toBeTrue();

        $this->booking->confirm();
        expect($this->booking->holdHasEnded(new DateTimeImmutable('2026-11-01T13:00:00Z')))->toBeFalse();
    });

    it('expires once the deadline has passed, freeing the slot', function (): void {
        $this->booking->expire(new DateTimeImmutable('2026-11-01T12:15:00Z'));

        $events = $this->booking->releaseEvents();
        expect($this->booking->status())->toBe(BookingStatus::Expired)
            ->and($this->booking->holdsSlot())->toBeFalse()
            ->and($events)->toHaveCount(1)
            ->and($events[0]::name())->toBe('booking.expired')
            ->and($events[0]->payload())->toBe([
                'booking_id' => $this->booking->id->value,
                'space_id' => $this->space->value,
                'customer_id' => 7,
                'start' => '2026-11-02T10:00:00+00:00',
                'end' => '2026-11-02T11:00:00+00:00',
            ]);
    });

    it('cannot expire before the deadline', function (): void {
        $this->booking->expire(new DateTimeImmutable('2026-11-01T12:14:59Z'));
    })->throws(InvalidBookingTransition::class, 'can still be paid until 2026-11-01T12:15:00+00:00');

    it('never expires a paid booking', function (): void {
        $this->booking->confirm();
        $this->booking->expire(new DateTimeImmutable('2026-11-01T13:00:00Z'));
    })->throws(InvalidBookingTransition::class, 'cannot go from confirmed to expired');

    it('is final once expired', function (Closure $next): void {
        $this->booking->expire(new DateTimeImmutable('2026-11-01T12:15:00Z'));

        $next($this->booking);
    })->with([
        'pay' => [fn (Booking $b) => $b->confirm()],
        'cancel' => [fn (Booking $b): Booking => Builders::cancel($b)],
    ])->throws(InvalidBookingTransition::class);
});
