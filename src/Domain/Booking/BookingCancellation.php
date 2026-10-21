<?php

declare(strict_types=1);

namespace Domain\Booking;

use Domain\Shared\Money;

/**
 * When a booking was cancelled (normalised to UTC, like TimeSlot) and how much was refunded.
 */
final readonly class BookingCancellation
{
    public \DateTimeImmutable $cancelledAt;

    public function __construct(\DateTimeImmutable $cancelledAt, public Money $refund)
    {
        $this->cancelledAt = $cancelledAt->setTimezone(new \DateTimeZone('UTC'));
    }
}
