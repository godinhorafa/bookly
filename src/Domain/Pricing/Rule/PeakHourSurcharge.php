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
 * Adds a percentage of the hourly rate, but only for the minutes that fall in peak windows.
 * A 10:00-20:00 booking with an 18:00 peak pays the surcharge on 2 hours, not 10.
 */
final readonly class PeakHourSurcharge implements PricingRule
{
    /** @var list<PeakWindow> */
    private array $windows;

    /**
     * Windows must not overlap each other, or shared minutes would be charged twice.
     */
    public function __construct(private int $percent, PeakWindow ...$windows)
    {
        if ($percent < 1 || $percent > 100) {
            throw InvalidPricingRule::percentOutOfRange($percent);
        }

        $this->windows = array_values($windows);
    }

    public function apply(Quote $quote, PricingContext $context): Quote
    {
        $peakMinutes = 0;
        foreach ($this->windows as $window) {
            $peakMinutes += $window->minutesWithin($context->slot, $context->space->timezone);
        }

        // rate * minutes/60 * percent/100, rounded once
        $surcharge = $context->space->hourlyRate->ratio($peakMinutes * $this->percent, 60 * 100);

        return $quote->with(new Adjustment(
            'peak_hours',
            "Peak hours (+{$this->percent}% on {$peakMinutes} min)",
            AdjustmentKind::Surcharge,
            $surcharge,
        ));
    }
}
