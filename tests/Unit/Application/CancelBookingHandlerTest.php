<?php

declare(strict_types=1);

use Application\Booking\CancelBooking;
use Application\Booking\CancelBookingHandler;
use Application\Payment\PayForBooking;
use Application\Payment\PayForBookingHandler;
use Domain\Booking\BookingId;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingAlreadyStarted;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Cancellation\CancellationPolicyType;
use Domain\Payment\ChargeResult;
use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\PaymentGateway;
use Domain\Payment\PaymentGateways;
use Domain\Payment\PaymentStatus;
use Domain\Shared\Money;
use Infrastructure\Payment\FakePaymentGateway;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;
use Tests\Support\InlineTransaction;
use Tests\Support\InMemory\InMemoryBookingRepository;
use Tests\Support\InMemory\InMemoryPaymentRepository;
use Tests\Support\InMemory\InMemorySpaceRepository;

beforeEach(function (): void {
    $this->spaces = new InMemorySpaceRepository;
    $this->bookings = new InMemoryBookingRepository;
    $this->payments = new InMemoryPaymentRepository;
    $this->gateway = new FakePaymentGateway;
    $this->clock = new FrozenClock;
    $this->handler = new CancelBookingHandler(
        $this->bookings,
        $this->spaces,
        $this->payments,
        new PaymentGateways($this->gateway),
        new InlineTransaction,
        $this->clock,
    );

    $this->owner = new CustomerId(42);
});

/**
 * Books 2026-11-02T10:00Z -> 11:00Z for BRL 50.00 and pays for it through the real use case.
 */
function paidBookingIn(CancellationPolicyType $policy): BookingId
{
    $space = Builders::space(policy: $policy);
    test()->spaces->save($space);

    $booking = Builders::booking($space->id, test()->owner);
    test()->bookings->save($booking);

    (new PayForBookingHandler(test()->bookings, test()->payments, new PaymentGateways(test()->gateway), new InlineTransaction))
        ->handle(new PayForBooking($booking->id->value, 42, 'pm_card_visa'));

    return $booking->id;
}

it('refunds through the gateway according to the space policy', function (CancellationPolicyType $policy, int $expected, PaymentStatus $status): void {
    $id = paidBookingIn($policy);
    $this->clock->travelTo('2026-11-01T04:00:00Z'); // 30h before

    $refund = $this->handler->handle(new CancelBooking($id->value, 42));

    $payment = $this->payments->forBooking($id)[0];
    expect($refund->amount)->toBe($expected)
        ->and($this->bookings->get($id)->status())->toBe(BookingStatus::Cancelled)
        ->and($payment->refunded()->amount)->toBe($expected)
        ->and($payment->status())->toBe($status);
})->with([
    'flexible: full refund' => [CancellationPolicyType::Flexible, 5_000, PaymentStatus::Refunded],
    'moderate: half refund' => [CancellationPolicyType::Moderate, 2_500, PaymentStatus::PartiallyRefunded],
    'strict: nothing back' => [CancellationPolicyType::Strict, 0, PaymentStatus::Succeeded],
]);

it('records the gateway refund reference', function (): void {
    $id = paidBookingIn(CancellationPolicyType::Flexible);

    $this->handler->handle(new CancelBooking($id->value, 42));

    expect($this->payments->forBooking($id)[0]->refundReference())->toStartWith('re_fake_');
});

it('cancels an unpaid booking without touching the gateway', function (): void {
    $space = Builders::space();
    $this->spaces->save($space);
    $booking = Builders::booking($space->id, $this->owner);
    $this->bookings->save($booking);

    $refund = $this->handler->handle(new CancelBooking($booking->id->value, 42));

    expect($refund->isZero())->toBeTrue()
        ->and($this->bookings->get($booking->id)->status())->toBe(BookingStatus::Cancelled);
});

it('leaves the booking active when the refund cannot be sent', function (): void {
    $id = paidBookingIn(CancellationPolicyType::Flexible);
    $down = new class implements PaymentGateway
    {
        public function name(): string
        {
            return 'fake';
        }

        public function charge(string $idempotencyKey, Money $amount, string $paymentMethod, string $description): ChargeResult
        {
            throw PaymentGatewayUnavailable::because('fake', 'down');
        }

        public function refund(string $idempotencyKey, string $chargeReference, Money $amount): string
        {
            throw PaymentGatewayUnavailable::because('fake', 'down');
        }
    };
    $handler = new CancelBookingHandler($this->bookings, $this->spaces, $this->payments, new PaymentGateways($down), new InlineTransaction, $this->clock);

    expect(fn (): Money => $handler->handle(new CancelBooking($id->value, 42)))->toThrow(PaymentGatewayUnavailable::class);

    expect($this->bookings->get($id)->status())->toBe(BookingStatus::Confirmed)
        ->and($this->payments->forBooking($id)[0]->refunded()->isZero())->toBeTrue();
});

it('hides bookings that belong to someone else', function (): void {
    $id = paidBookingIn(CancellationPolicyType::Flexible);

    $this->handler->handle(new CancelBooking($id->value, 999));
})->throws(BookingNotFound::class);

it('refuses to cancel after the start', function (): void {
    $id = paidBookingIn(CancellationPolicyType::Flexible);
    $this->clock->travelTo('2026-11-02T10:15:00Z');

    $this->handler->handle(new CancelBooking($id->value, 42));
})->throws(BookingAlreadyStarted::class);
