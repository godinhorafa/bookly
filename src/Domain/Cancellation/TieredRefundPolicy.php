<?php

declare(strict_types=1);

namespace Domain\Cancellation;

use Domain\Cancellation\Exception\InvalidCancellationPolicy;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;

/**
 * Picks the most generous tier whose notice period the cancellation still meets.
 * Cancelling with less notice than every tier refunds nothing.
 */
final readonly class TieredRefundPolicy implements CancellationPolicy
{
    /** @var list<RefundTier> ordered from longest to shortest notice */
    private array $tiers;

    public function __construct(RefundTier ...$tiers)
    {
        usort($tiers, static fn (RefundTier $a, RefundTier $b): int => $b->minimumHoursBefore <=> $a->minimumHoursBefore);

        $hours = array_map(static fn (RefundTier $t): int => $t->minimumHoursBefore, $tiers);
        foreach (array_count_values($hours) as $h => $count) {
            if ($count > 1) {
                throw InvalidCancellationPolicy::duplicateTier($h);
            }
        }

        $this->tiers = $tiers;
    }

    public function refundFor(Money $paid, TimeSlot $slot, \DateTimeImmutable $cancelledAt): Money
    {
        $secondsOfNotice = $slot->start->getTimestamp() - $cancelledAt->getTimestamp();

        foreach ($this->tiers as $tier) {
            if ($secondsOfNotice >= $tier->minimumHoursBefore * 3600) {
                return $paid->percentage($tier->refundPercent);
            }
        }

        return Money::zero($paid->currency);
    }
}
