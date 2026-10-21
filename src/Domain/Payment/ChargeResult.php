<?php

declare(strict_types=1);

namespace Domain\Payment;

final readonly class ChargeResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $reference,
        public ?string $declineReason,
    ) {}

    public static function succeeded(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function declined(string $reason, ?string $reference = null): self
    {
        return new self(false, $reference, $reason);
    }
}
