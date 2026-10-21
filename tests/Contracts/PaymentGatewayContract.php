<?php

declare(strict_types=1);

use Domain\Payment\Exception\RefundRejected;
use Domain\Payment\PaymentGateway;
use Domain\Shared\Currency;
use Domain\Shared\Money;

/**
 * Behaviour every PaymentGateway adapter must have. It runs against the fake,
 * against the Stripe adapter with an offline emulator, and against real Stripe
 * test mode when STRIPE_TEST_SECRET is set, so the three can't silently diverge.
 *
 * Keys are unique per run because real providers remember them for 24 hours.
 *
 * @param  Closure(): PaymentGateway  $setUp
 */
function paymentGatewayContract(Closure $setUp): void
{
    beforeEach(function () use ($setUp): void {
        $this->gateway = $setUp();
        $this->run = bin2hex(random_bytes(6));
        $this->key = fn (string $name): string => "contract-{$this->run}-{$name}";
    });

    it('charges a valid card and returns a reference', function (): void {
        $result = $this->gateway->charge(($this->key)('ok'), Money::of(5_000, Currency::BRL), 'pm_card_visa', 'Contract test');

        expect($result->succeeded)->toBeTrue()
            ->and($result->reference)->toBeString()->not->toBeEmpty()
            ->and($result->declineReason)->toBeNull();
    });

    it('reports a declined card as a normal outcome, not an error', function (string $method, string $reason): void {
        $result = $this->gateway->charge(($this->key)("declined-{$reason}"), Money::of(5_000, Currency::BRL), $method, 'Contract test');

        expect($result->succeeded)->toBeFalse()
            ->and($result->declineReason)->toBe($reason);
    })->with([
        'generic decline' => ['pm_card_chargeDeclined', 'generic_decline'],
        'insufficient funds' => ['pm_card_visa_chargeDeclinedInsufficientFunds', 'insufficient_funds'],
    ]);

    it('never charges twice for the same idempotency key', function (): void {
        $key = ($this->key)('replay');

        $first = $this->gateway->charge($key, Money::of(5_000, Currency::BRL), 'pm_card_visa', 'Contract test');
        $retry = $this->gateway->charge($key, Money::of(5_000, Currency::BRL), 'pm_card_visa', 'Contract test');

        expect($retry->reference)->toBe($first->reference);
    });

    it('refunds in parts up to the charged amount, and no further', function (): void {
        $charge = $this->gateway->charge(($this->key)('to-refund'), Money::of(5_000, Currency::BRL), 'pm_card_visa', 'Contract test');
        $reference = (string) $charge->reference;

        $first = $this->gateway->refund(($this->key)('refund-1'), $reference, Money::of(2_000, Currency::BRL));
        $second = $this->gateway->refund(($this->key)('refund-2'), $reference, Money::of(3_000, Currency::BRL));

        expect($first)->not->toBe($second)
            ->and(fn () => $this->gateway->refund(($this->key)('refund-3'), $reference, Money::of(1, Currency::BRL)))
            ->toThrow(RefundRejected::class);
    });

    it('never refunds twice for the same idempotency key', function (): void {
        $charge = $this->gateway->charge(($this->key)('refund-replay'), Money::of(5_000, Currency::BRL), 'pm_card_visa', 'Contract test');
        $key = ($this->key)('refund-replay-r');

        $first = $this->gateway->refund($key, (string) $charge->reference, Money::of(5_000, Currency::BRL));
        $retry = $this->gateway->refund($key, (string) $charge->reference, Money::of(5_000, Currency::BRL));

        expect($retry)->toBe($first);
    });
}
