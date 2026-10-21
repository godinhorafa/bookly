<?php

declare(strict_types=1);

namespace Domain\Pricing\Rule;

use Domain\Pricing\Adjustment;
use Domain\Pricing\AdjustmentKind;
use Domain\Pricing\Exception\InvalidPricingRule;
use Domain\Pricing\PricingContext;
use Domain\Pricing\PricingRule;
use Domain\Pricing\Quote;

/**
 * Percentage off the running total for bookings of at least a given length, e.g. 10% off 4h+.
 */
final readonly class LongBookingDiscount implements PricingRule
{
    public function __construct(private int $minimumMinutes, private int $percent)
    {
        if ($minimumMinutes < 1) {
            throw InvalidPricingRule::invalidMinimumDuration($minimumMinutes);
        }

        if ($percent < 1 || $percent > 100) {
            throw InvalidPricingRule::percentOutOfRange($percent);
        }
    }

    public function apply(Quote $quote, PricingContext $context): Quote
    {
        if ($context->slot->durationInMinutes() < $this->minimumMinutes) {
            return $quote;
        }

        return $quote->with(new Adjustment(
            'long_booking',
            "Long booking ({$this->percent}% off {$this->minimumMinutes}+ min)",
            AdjustmentKind::Discount,
            $quote->total->percentage($this->percent),
        ));
    }
}
