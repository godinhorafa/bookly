<?php

declare(strict_types=1);

use Domain\Booking\BookingId;
use Domain\Payment\ChargeResult;
use Domain\Payment\Exception\InvalidRefund;
use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\Payment;
use Domain\Payment\PaymentGateway;
use Domain\Payment\PaymentGateways;
use Domain\Payment\PaymentId;
use Domain\Payment\PaymentStatus;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Infrastructure\Payment\FakePaymentGateway;

function aPayment(ChargeResult $result): Payment
{
    return Payment::fromCharge(PaymentId::generate(), BookingId::generate(), 1, Money::of(5_000, Currency::BRL), 'fake', $result);
}

it('tracks partial and full refunds', function (): void {
    $payment = aPayment(ChargeResult::succeeded('ch_1'));

    $payment->recordRefund(Money::of(2_000, Currency::BRL), 're_1');
    expect($payment->status())->toBe(PaymentStatus::PartiallyRefunded)
        ->and($payment->refundable()->amount)->toBe(3_000);

    $payment->recordRefund(Money::of(3_000, Currency::BRL), 're_2');
    expect($payment->status())->toBe(PaymentStatus::Refunded)
        ->and($payment->refundable()->isZero())->toBeTrue()
        ->and($payment->refundReference())->toBe('re_2');
});

it('never refunds more than was paid', function (): void {
    aPayment(ChargeResult::succeeded('ch_1'))->recordRefund(Money::of(5_001, Currency::BRL), 're_1');
})->throws(InvalidRefund::class, 'only BRL 50.00 is refundable');

it('has nothing to refund on a declined attempt', function (): void {
    $declined = aPayment(ChargeResult::declined('generic_decline'));

    expect($declined->succeeded())->toBeFalse()
        ->and($declined->refundable()->isZero())->toBeTrue()
        ->and(fn (): string => $declined->chargeReference())->toThrow(LogicException::class);
});

it('never treats the reference of a declined charge as a charge to refund', function (): void {
    // Stripe returns a PaymentIntent id even when the card is declined.
    $declined = aPayment(ChargeResult::declined('insufficient_funds', 'pi_declined'));

    expect(fn (): string => $declined->chargeReference())->toThrow(LogicException::class);
});

it('routes refunds to the gateway that took the charge', function (): void {
    $fake = new FakePaymentGateway;
    $gateways = new PaymentGateways($fake);

    expect($gateways->default())->toBe($fake)
        ->and($gateways->named('fake'))->toBe($fake)
        ->and(fn (): PaymentGateway => $gateways->named('paypal'))->toThrow(PaymentGatewayUnavailable::class, 'not configured');
});

it('keeps the other gateways reachable for refunds', function (): void {
    $stripe = Mockery::mock(PaymentGateway::class);
    $stripe->allows('name')->andReturn('stripe');

    expect((new PaymentGateways(new FakePaymentGateway, $stripe))->named('stripe'))->toBe($stripe);
});
