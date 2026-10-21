<?php

declare(strict_types=1);

namespace Domain\Pricing\Rule;

use Domain\Pricing\Adjustment;
use Domain\Pricing\AdjustmentKind;
use Domain\Pricing\Coupon;
use Domain\Pricing\PricingContext;
use Domain\Pricing\PricingRule;
use Domain\Pricing\Quote;

/**
 * Applies the customer's coupon, if any, to the running total.
 * Validity (expiry, existence) is checked before pricing, when the coupon is resolved.
 */
final readonly class CouponDiscount implements PricingRule
{
    public function apply(Quote $quote, PricingContext $context): Quote
    {
        if (! $context->coupon instanceof Coupon) {
            return $quote;
        }

        return $quote->with(new Adjustment(
            'coupon',
            "Coupon {$context->coupon->code}",
            AdjustmentKind::Discount,
            $context->coupon->discountOn($quote->total),
        ));
    }
}
