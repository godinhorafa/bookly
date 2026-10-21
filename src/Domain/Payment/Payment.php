<?php

declare(strict_types=1);

namespace Domain\Payment;

use Domain\Booking\BookingId;
use Domain\Payment\Exception\InvalidRefund;
use Domain\Shared\Money;

/**
 * One attempt to pay for a booking, as recorded by us (the gateway keeps its own record).
 * Declined attempts are kept too: they explain why a booking is still pending.
 */
final class Payment
{
    private function __construct(
        public readonly PaymentId $id,
        public readonly BookingId $bookingId,
        public readonly int $attempt,
        public readonly Money $amount,
        public readonly string $gateway,
        public readonly ?string $reference,
        private PaymentStatus $status,
        private Money $refunded,
        public readonly ?string $declineReason = null,
        private ?string $refundReference = null,
    ) {}

    public static function fromCharge(PaymentId $id, BookingId $bookingId, int $attempt, Money $amount, string $gateway, ChargeResult $result): self
    {
        return new self(
            $id,
            $bookingId,
            $attempt,
            $amount,
            $gateway,
            $result->reference,
            $result->succeeded ? PaymentStatus::Succeeded : PaymentStatus::Declined,
            Money::zero($amount->currency),
            $result->declineReason,
        );
    }

    public static function reconstitute(
        PaymentId $id,
        BookingId $bookingId,
        int $attempt,
        Money $amount,
        string $gateway,
        ?string $reference,
        PaymentStatus $status,
        Money $refunded,
        ?string $declineReason,
        ?string $refundReference,
    ): self {
        return new self($id, $bookingId, $attempt, $amount, $gateway, $reference, $status, $refunded, $declineReason, $refundReference);
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function refunded(): Money
    {
        return $this->refunded;
    }

    public function succeeded(): bool
    {
        return $this->status !== PaymentStatus::Declined;
    }

    /**
     * The provider's reference for a successful charge.
     */
    public function chargeReference(): string
    {
        if (! $this->succeeded() || $this->reference === null) {
            throw new \LogicException("Payment {$this->id} has no successful charge to refer to.");
        }

        return $this->reference;
    }

    public function refundReference(): ?string
    {
        return $this->refundReference;
    }

    public function refundable(): Money
    {
        return $this->succeeded() ? $this->amount->subtract($this->refunded) : Money::zero($this->amount->currency);
    }

    public function recordRefund(Money $amount, string $refundReference): void
    {
        if ($amount->isZero() || $amount->greaterThan($this->refundable())) {
            throw InvalidRefund::exceedsRefundable($this->id, $amount, $this->refundable());
        }

        $this->refunded = $this->refunded->add($amount);
        $this->refundReference = $refundReference;
        $this->status = $this->refunded->equals($this->amount) ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
    }
}
