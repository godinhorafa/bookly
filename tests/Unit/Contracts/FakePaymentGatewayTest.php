<?php

declare(strict_types=1);

use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Infrastructure\Payment\FakePaymentGateway;

require_once __DIR__ . '/../../Contracts/PaymentGatewayContract.php';

paymentGatewayContract(fn (): FakePaymentGateway => new FakePaymentGateway);

it('can simulate an outage', function (): void {
    (new FakePaymentGateway)->charge('k', Money::of(100, Currency::BRL), 'pm_fake_gateway_down', 'outage');
})->throws(PaymentGatewayUnavailable::class);

it('keeps its ledger across processes that share a cache store', function (): void {
    // Two instances on one store stand in for two PHP processes behind a web server.
    $store = new Repository(new ArrayStore(serializesValues: true));

    $charge = (new FakePaymentGateway($store))->charge('k-charge', Money::of(9_000, Currency::BRL), 'pm_card_visa', 'booking');
    $refund = (new FakePaymentGateway($store))->refund('k-refund', (string) $charge->reference, Money::of(9_000, Currency::BRL));
    $replay = (new FakePaymentGateway($store))->charge('k-charge', Money::of(9_000, Currency::BRL), 'pm_card_visa', 'booking');

    expect($refund)->toStartWith('re_fake_')
        ->and($replay->reference)->toBe($charge->reference);
});
