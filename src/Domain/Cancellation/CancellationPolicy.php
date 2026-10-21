<?php

declare(strict_types=1);

namespace Domain\Cancellation;

use Domain\Shared\Money;
use Domain\Shared\TimeSlot;

/**
 * Strategy: how much of what was paid goes back to the customer on cancellation.
 */
interface CancellationPolicy
{
    public function refundFor(Money $paid, TimeSlot $slot, \DateTimeImmutable $cancelledAt): Money;
}
