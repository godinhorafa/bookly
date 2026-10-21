<?php

declare(strict_types=1);

namespace Domain\Pricing;

/**
 * Strategy: one independent pricing concern (peak hours, long bookings, coupons...).
 * A rule receives the quote built so far and returns it, possibly with one more adjustment.
 */
interface PricingRule
{
    public function apply(Quote $quote, PricingContext $context): Quote;
}
