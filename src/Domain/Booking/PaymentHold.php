<?php

declare(strict_types=1);

namespace Domain\Booking;

/**
 * How long a new booking holds its slot while waiting for payment. Without a
 * limit, an abandoned checkout would block the slot for everyone, forever.
 */
final readonly class PaymentHold
{
    public function __construct(public int $minutes)
    {
        if ($minutes < 1) {
            throw new \InvalidArgumentException("A payment hold must last at least 1 minute, got {$minutes}.");
        }
    }

    public function from(\DateTimeImmutable $requestedAt): \DateTimeImmutable
    {
        return $requestedAt->modify("+{$this->minutes} minutes");
    }
}
