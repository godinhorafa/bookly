<?php

declare(strict_types=1);

use Application\Payment\PayForBooking;
use Application\Payment\PayForBookingHandler;
use Application\Payment\PaymentOutcome;
use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Payment\Exception\BookingNotPayable;
use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\PaymentGateways;
use Domain\Payment\PaymentStatus;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Space\SpaceId;
use Infrastructure\Payment\FakePaymentGateway;
use Tests\Support\Builders;
use Tests\Support\InlineTransaction;
use Tests\Support\InMemory\InMemoryBookingRepository;
use Tests\Support\InMemory\InMemoryPaymentRepository;

beforeEach(function (): void {
    $this->bookings = new InMemoryBookingRepository;
    $this->payments = new InMemoryPaymentRepository;
    $this->gateway = new FakePaymentGateway;
    $this->transaction = new InlineTransaction;
    $this->handler = new PayForBookingHandler($this->bookings, $this->payments, new PaymentGateways($this->gateway), $this->transaction);

    $this->booking = Builders::booking(SpaceId::generate(), new CustomerId(42)); // BRL 50.00, pending
    $this->bookings->save($this->booking);
});

function pay(string $method = 'pm_card_visa', int $customer = 42): PaymentOutcome
{
    return test()->handler->handle(new PayForBooking(test()->booking->id->value, $customer, $method));
}

it('charges the booking price and confirms the booking', function (): void {
    $outcome = pay();

    $payments = $this->payments->forBooking($this->booking->id);
    expect($outcome->confirmed)->toBeTrue()
        ->and($this->bookings->get($this->booking->id)->status())->toBe(BookingStatus::Confirmed)
        ->and($payments)->toHaveCount(1)
        ->and($payments[0]->status())->toBe(PaymentStatus::Succeeded)
        ->and($payments[0]->amount->equals(Money::of(5_000, Currency::BRL)))->toBeTrue()
        ->and($payments[0]->gateway)->toBe('fake')
        ->and($outcome->paymentId)->toBe($payments[0]->id->value);
});

it('keeps the booking pending when the card is declined, and records why', function (): void {
    $outcome = pay('pm_card_chargeDeclined');

    expect($outcome->confirmed)->toBeFalse()
        ->and($outcome->declineReason)->toBe('generic_decline')
        ->and($this->bookings->get($this->booking->id)->status())->toBe(BookingStatus::Pending)
        ->and($this->payments->forBooking($this->booking->id)[0]->status())->toBe(PaymentStatus::Declined);
});

it('lets the customer retry with another card after a decline', function (): void {
    pay('pm_card_chargeDeclined');
    $outcome = pay('pm_card_visa');

    // A new attempt uses a new idempotency key, otherwise the gateway would replay the decline.
    expect($outcome->confirmed)->toBeTrue()
        ->and($this->payments->forBooking($this->booking->id))->toHaveCount(2);
});

it('replays the same charge when saving failed after the gateway took the money', function (): void {
    $this->transaction->failNext();
    expect(fn (): PaymentOutcome => pay())->toThrow(RuntimeException::class, 'Simulated database failure');
    expect($this->bookings->get($this->booking->id)->status())->toBe(BookingStatus::Pending);

    pay();

    // Same attempt number, same key: the fake (like Stripe) returns the original charge.
    $payments = $this->payments->forBooking($this->booking->id);
    expect($payments)->toHaveCount(1)
        ->and($payments[0]->reference)->toBe('ch_fake_000001');
});

it('saves nothing when the gateway is down', function (): void {
    expect(fn (): PaymentOutcome => pay('pm_fake_gateway_down'))->toThrow(PaymentGatewayUnavailable::class);

    expect($this->payments->forBooking($this->booking->id))->toBe([])
        ->and($this->bookings->get($this->booking->id)->status())->toBe(BookingStatus::Pending);
});

it('confirms a fully discounted booking without charging anything', function (): void {
    $free = Booking::request(BookingId::generate(), SpaceId::generate(), new CustomerId(42), TimeSlot::fromStrings('2026-11-05T10:00:00Z', '2026-11-05T11:00:00Z'), Money::zero(Currency::BRL));
    $this->bookings->save($free);

    $outcome = $this->handler->handle(new PayForBooking($free->id->value, 42, 'pm_card_visa'));

    expect($outcome->confirmed)->toBeTrue()
        ->and($outcome->paymentId)->toBeNull()
        ->and($this->payments->forBooking($free->id))->toBe([])
        ->and($this->bookings->get($free->id)->status())->toBe(BookingStatus::Confirmed);
});

it('refuses to pay twice', function (): void {
    pay();
    pay();
})->throws(BookingNotPayable::class, 'is confirmed and cannot be paid');

it('hides bookings that belong to someone else', function (): void {
    pay(customer: 7);
})->throws(BookingNotFound::class);
