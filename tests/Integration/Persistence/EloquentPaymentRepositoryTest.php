<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Booking\Booking;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Payment\ChargeResult;
use Domain\Payment\Payment;
use Domain\Payment\PaymentId;
use Domain\Payment\PaymentRepository;
use Domain\Payment\PaymentStatus;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Space\SpaceRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Builders;

beforeEach(function (): void {
    $space = Builders::space();
    app(SpaceRepository::class)->save($space);
    $this->booking = Builders::booking($space->id, new CustomerId(User::factory()->create()->id));
    app(BookingRepository::class)->save($this->booking);
    $this->repository = app(PaymentRepository::class);
});

function chargeFor(Booking $booking, ChargeResult $result, int $attempt = 1): Payment
{
    return Payment::fromCharge(PaymentId::generate(), $booking->id, $attempt, Money::of(5_000, Currency::BRL), 'fake', $result);
}

it('round-trips payments in attempt order', function (): void {
    $declined = chargeFor($this->booking, ChargeResult::declined('insufficient_funds'));
    $this->repository->save($declined);
    $paid = chargeFor($this->booking, ChargeResult::succeeded('ch_123'), attempt: 2);
    $this->repository->save($paid);

    $paid->recordRefund(Money::of(1_500, Currency::BRL), 're_9');
    $this->repository->save($paid);

    [$first, $second] = $this->repository->forBooking($this->booking->id);
    expect($first->status())->toBe(PaymentStatus::Declined)
        ->and($first->declineReason)->toBe('insufficient_funds')
        ->and($second->status())->toBe(PaymentStatus::PartiallyRefunded)
        ->and($second->refunded()->amount)->toBe(1_500)
        ->and($second->chargeReference())->toBe('ch_123')
        ->and($second->refundReference())->toBe('re_9');
});

it('refuses a second successful charge for the same booking', function (): void {
    $this->repository->save(chargeFor($this->booking, ChargeResult::succeeded('ch_1')));

    expect(fn () => DB::transaction(fn () => $this->repository->save(chargeFor($this->booking, ChargeResult::succeeded('ch_2'), attempt: 2))))
        ->toThrow(QueryException::class, 'payments_one_settled_charge_per_booking');
});

it('refuses to record the same attempt twice', function (): void {
    $this->repository->save(chargeFor($this->booking, ChargeResult::declined('generic_decline')));

    expect(fn () => DB::transaction(fn () => $this->repository->save(chargeFor($this->booking, ChargeResult::declined('generic_decline')))))
        ->toThrow(QueryException::class, 'payments_booking_id_attempt_unique');
});
