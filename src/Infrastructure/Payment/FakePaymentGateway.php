<?php

declare(strict_types=1);

namespace Infrastructure\Payment;

use Domain\Payment\ChargeResult;
use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\Exception\RefundRejected;
use Domain\Payment\PaymentGateway;
use Domain\Shared\Money;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

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
final readonly class FakePaymentGateway implements PaymentGateway
{
    private const string PREFIX = 'fake-payment-gateway:';

    /**
     * The ledger lives in a cache store, not in PHP memory: behind a real web
     * server every request is a fresh process, and a refund must still find
     * the charge made by an earlier request. Without a store it stays in memory.
     */
    private CacheRepository $ledger;

    public function __construct(?CacheRepository $ledger = null)
    {
        $this->ledger = $ledger ?? new Repository(new ArrayStore(serializesValues: true));
    }

    public function name(): string
    {
        return 'fake';
    }

    public function charge(string $idempotencyKey, Money $amount, string $paymentMethod, string $description): ChargeResult
    {
        /** @var array{succeeded: bool, reference: ?string, decline_reason: ?string}|null $replay */
        $replay = $this->ledger->get(self::PREFIX . "charge-key:{$idempotencyKey}");
        if ($replay !== null) {
            return $replay['succeeded'] && $replay['reference'] !== null
                ? ChargeResult::succeeded($replay['reference'])
                : ChargeResult::declined((string) $replay['decline_reason'], $replay['reference']);
        }

        $result = match ($paymentMethod) {
            'pm_card_visa', 'pm_card_mastercard' => ChargeResult::succeeded($this->nextReference('ch')),
            'pm_card_chargeDeclined' => ChargeResult::declined('generic_decline'),
            'pm_card_visa_chargeDeclinedInsufficientFunds' => ChargeResult::declined('insufficient_funds'),
            'pm_fake_gateway_down' => throw PaymentGatewayUnavailable::because($this->name(), 'simulated outage'),
            default => ChargeResult::declined('invalid_payment_method'),
        };

        if ($result->succeeded && $result->reference !== null) {
            $this->ledger->forever(self::PREFIX . "charge:{$result->reference}", ['amount' => $amount->amount, 'currency' => $amount->currency->value, 'refunded' => 0]);
        }

        // Plain arrays only: the cache refuses to unserialize arbitrary objects.
        $this->ledger->forever(self::PREFIX . "charge-key:{$idempotencyKey}", [
            'succeeded' => $result->succeeded,
            'reference' => $result->reference,
            'decline_reason' => $result->declineReason,
        ]);

        return $result;
    }

    public function refund(string $idempotencyKey, string $chargeReference, Money $amount): string
    {
        $replay = $this->ledger->get(self::PREFIX . "refund-key:{$idempotencyKey}");
        if (is_string($replay)) {
            return $replay;
        }

        /** @var array{amount: int, currency: string, refunded: int}|null $charge */
        $charge = $this->ledger->get(self::PREFIX . "charge:{$chargeReference}");
        if ($charge === null) {
            throw RefundRejected::byGateway($this->name(), "no charge {$chargeReference}");
        }

        $left = $charge['amount'] - $charge['refunded'];
        if ($amount->currency->value !== $charge['currency'] || $amount->amount > $left) {
            throw RefundRejected::byGateway($this->name(), "amount exceeds the {$left} minor units left to refund");
        }

        $charge['refunded'] += $amount->amount;
        $this->ledger->forever(self::PREFIX . "charge:{$chargeReference}", $charge);

        $reference = $this->nextReference('re');
        $this->ledger->forever(self::PREFIX . "refund-key:{$idempotencyKey}", $reference);

        return $reference;
    }

    private function nextReference(string $prefix): string
    {
        $sequence = $this->ledger->increment(self::PREFIX . 'sequence');

        return sprintf('%s_fake_%06d', $prefix, is_int($sequence) ? $sequence : 0);
    }
}
