<?php

declare(strict_types=1);

namespace Domain\Cancellation;

use Domain\Booking\TimeSlot;
use Domain\Shared\Money;

/**
 * Strategy: how much of what was paid goes back to the customer on cancellation.
 */
interface CancellationPolicy
{
    public function refundFor(Money $paid, TimeSlot $slot, \DateTimeImmutable $cancelledAt): Money;
}
