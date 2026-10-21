<?php

declare(strict_types=1);

namespace Domain\Cancellation;

/**
 * The policies a space owner can choose from. Each one maps to a concrete strategy.
 */
enum CancellationPolicyType: string
{
    /** 100% up to 24h before, 50% after that. */
    case Flexible = 'flexible';

    /** 100% up to 48h before, 50% up to 24h before, nothing after. */
    case Moderate = 'moderate';

    /** 50% up to 7 days before, nothing after. */
    case Strict = 'strict';

    case NonRefundable = 'non_refundable';

    public function policy(): CancellationPolicy
    {
        return match ($this) {
            self::Flexible => new TieredRefundPolicy(new RefundTier(24, 100), new RefundTier(0, 50)),
            self::Moderate => new TieredRefundPolicy(new RefundTier(48, 100), new RefundTier(24, 50)),
            self::Strict => new TieredRefundPolicy(new RefundTier(7 * 24, 50)),
            self::NonRefundable => new TieredRefundPolicy,
        };
    }
}
