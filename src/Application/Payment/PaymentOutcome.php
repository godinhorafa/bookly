<?php

declare(strict_types=1);

namespace Application\Payment;

final readonly class PaymentOutcome
{
    private function __construct(
        public bool $confirmed,
        public ?string $paymentId,
        public ?string $declineReason,
    ) {}

    public static function confirmed(?string $paymentId): self
    {
        return new self(true, $paymentId, null);
    }

    public static function declined(string $paymentId, string $reason): self
    {
        return new self(false, $paymentId, $reason);
    }
}
