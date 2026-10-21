<?php

declare(strict_types=1);

use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Infrastructure\Payment\FakePaymentGateway;

require_once __DIR__ . '/../../Contracts/PaymentGatewayContract.php';

paymentGatewayContract(fn (): FakePaymentGateway => new FakePaymentGateway);

it('can simulate an outage', function (): void {
    (new FakePaymentGateway)->charge('k', Money::of(100, Currency::BRL), 'pm_fake_gateway_down', 'outage');
})->throws(PaymentGatewayUnavailable::class);
