<?php

declare(strict_types=1);

use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Infrastructure\Payment\StripePaymentGateway;
use Tests\Support\StripeEmulator;

require_once __DIR__ . '/../../Contracts/PaymentGatewayContract.php';

describe('against the offline Stripe emulator', function (): void {
    paymentGatewayContract(fn (): StripePaymentGateway => new StripePaymentGateway(
        (new StripeEmulator)->install(new Factory),
        'sk_test_emulated',
    ));
});

describe('against real Stripe test mode', function (): void {
    paymentGatewayContract(fn (): StripePaymentGateway => new StripePaymentGateway(new Factory, (string) getenv('STRIPE_TEST_SECRET')));
})->skip(fn (): bool => ! is_string(getenv('STRIPE_TEST_SECRET')) || getenv('STRIPE_TEST_SECRET') === '', 'Set STRIPE_TEST_SECRET to run the contract against Stripe test mode.');

it('sends an authenticated, form-encoded, idempotent request', function (): void {
    $emulator = new StripeEmulator;
    $gateway = new StripePaymentGateway($emulator->install(new Factory), 'sk_test_123');

    $gateway->charge('booking-abc-charge-1', Money::of(12_345, Currency::BRL), 'pm_card_visa', 'Booking abc');

    /** @var Request $request */
    $request = $emulator->requests[0];
    expect($request->url())->toBe('https://api.stripe.com/v1/payment_intents')
        ->and($request->header('Authorization'))->toBe(['Bearer sk_test_123'])
        ->and($request->header('Idempotency-Key'))->toBe(['booking-abc-charge-1'])
        ->and($request->isForm())->toBeTrue()
        ->and($request->data())->toMatchArray([
            'amount' => 12_345,
            'currency' => 'brl',
            'payment_method' => 'pm_card_visa',
            'confirm' => 'true',
            'automatic_payment_methods[allow_redirects]' => 'never',
        ]);
});

it('treats Stripe server errors as a retryable outage', function (): void {
    $gateway = new StripePaymentGateway((new StripeEmulator)->install(new Factory), 'sk_test_123');

    $gateway->charge('k', Money::of(5_000, Currency::BRL), 'pm_emulator_server_error', 'outage');
})->throws(PaymentGatewayUnavailable::class, 'HTTP 500');

it('treats network failures as a retryable outage', function (): void {
    $http = new Factory;
    $http->fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    (new StripePaymentGateway($http, 'sk_test_123'))->charge('k', Money::of(5_000, Currency::BRL), 'pm_card_visa', 'timeout');
})->throws(PaymentGatewayUnavailable::class, 'Operation timed out');
