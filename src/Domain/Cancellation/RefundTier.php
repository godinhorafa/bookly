<?php

declare(strict_types=1);

namespace Domain\Cancellation;

use Domain\Cancellation\Exception\InvalidCancellationPolicy;

/**
 * "Cancel at least N hours before the start and get P% back."
 */
final readonly class RefundTier
{
    public function __construct(
        public int $minimumHoursBefore,
        public int $refundPercent,
    ) {
        if ($minimumHoursBefore < 0) {
            throw InvalidCancellationPolicy::negativeHours($minimumHoursBefore);
        }

        if ($refundPercent < 0 || $refundPercent > 100) {
            throw InvalidCancellationPolicy::percentOutOfRange($refundPercent);
        }
    }
}
