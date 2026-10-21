<?php

declare(strict_types=1);

namespace Domain\Payment;

use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\Exception\RefundRejected;
use Domain\Shared\Money;

/**
 * Port for payment providers. Adapters (Stripe, a fake for tests...) translate
 * the provider's API and errors into these terms, so the rest of the system
 * never sees provider-specific types.
 *
 * Every call takes an idempotency key: retrying with the same key must never
 * charge or refund twice.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * A declined card is a normal outcome, returned as ChargeResult::declined().
     *
     * @throws PaymentGatewayUnavailable when the provider can't be reached; safe to retry with the same key
     */
    public function charge(string $idempotencyKey, Money $amount, string $paymentMethod, string $description): ChargeResult;

    /**
     * @return string the provider's refund reference
     *
     * @throws RefundRejected when the provider refuses the refund (e.g. more than what is left)
     * @throws PaymentGatewayUnavailable
     */
    public function refund(string $idempotencyKey, string $chargeReference, Money $amount): string;
}
