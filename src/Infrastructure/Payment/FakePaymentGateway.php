<?php

declare(strict_types=1);

namespace Infrastructure\Payment;

use Domain\Payment\ChargeResult;
use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\Exception\RefundRejected;
use Domain\Payment\PaymentGateway;
use Domain\Shared\Money;

/**
 * In-process gateway for local development, demos and tests. It accepts the same
 * test payment methods as Stripe's test mode, so switching gateways needs no
 * other change, and it honours idempotency keys exactly like a real provider.
 *
 *  - pm_card_visa, pm_card_mastercard               → succeeds
 *  - pm_card_chargeDeclined                         → declined: generic_decline
 *  - pm_card_visa_chargeDeclinedInsufficientFunds   → declined: insufficient_funds
 *  - pm_fake_gateway_down                           → PaymentGatewayUnavailable (fake only)
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, ChargeResult> */
    private array $chargesByKey = [];

    /** @var array<string, array{amount: Money, refunded: int}> */
    private array $charges = [];

    /** @var array<string, string> */
    private array $refundsByKey = [];

    private int $sequence = 0;

    public function name(): string
    {
        return 'fake';
    }

    public function charge(string $idempotencyKey, Money $amount, string $paymentMethod, string $description): ChargeResult
    {
        if (isset($this->chargesByKey[$idempotencyKey])) {
            return $this->chargesByKey[$idempotencyKey];
        }

        $result = match ($paymentMethod) {
            'pm_card_visa', 'pm_card_mastercard' => ChargeResult::succeeded($this->nextReference('ch')),
            'pm_card_chargeDeclined' => ChargeResult::declined('generic_decline'),
            'pm_card_visa_chargeDeclinedInsufficientFunds' => ChargeResult::declined('insufficient_funds'),
            'pm_fake_gateway_down' => throw PaymentGatewayUnavailable::because($this->name(), 'simulated outage'),
            default => ChargeResult::declined('invalid_payment_method'),
        };

        if ($result->succeeded && $result->reference !== null) {
            $this->charges[$result->reference] = ['amount' => $amount, 'refunded' => 0];
        }

        return $this->chargesByKey[$idempotencyKey] = $result;
    }

    public function refund(string $idempotencyKey, string $chargeReference, Money $amount): string
    {
        if (isset($this->refundsByKey[$idempotencyKey])) {
            return $this->refundsByKey[$idempotencyKey];
        }

        $charge = $this->charges[$chargeReference] ?? throw RefundRejected::byGateway($this->name(), "no charge {$chargeReference}");
        $left = $charge['amount']->amount - $charge['refunded'];

        if ($amount->currency !== $charge['amount']->currency || $amount->amount > $left) {
            throw RefundRejected::byGateway($this->name(), "amount exceeds the {$left} minor units left to refund");
        }

        $this->charges[$chargeReference]['refunded'] += $amount->amount;

        return $this->refundsByKey[$idempotencyKey] = $this->nextReference('re');
    }

    private function nextReference(string $prefix): string
    {
        return sprintf('%s_fake_%06d', $prefix, ++$this->sequence);
    }
}
