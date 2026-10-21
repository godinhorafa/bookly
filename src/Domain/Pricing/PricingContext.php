<?php

declare(strict_types=1);

namespace Domain\Pricing;

use Domain\Shared\TimeSlot;
use Domain\Space\Space;

/**
 * Everything a pricing rule may look at. Rules read from it and never change it.
 */
final readonly class PricingContext
{
    public function __construct(
        public Space $space,
        public TimeSlot $slot,
        public ?Coupon $coupon = null,
    ) {}
}
