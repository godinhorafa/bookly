<?php

declare(strict_types=1);

namespace Domain\Pricing;

use Domain\Shared\Money;

/**
 * Immutable price breakdown: the base price plus every adjustment applied, in order.
 * Keeping the lines (instead of a single number) makes the price explainable to the customer.
 */
final readonly class Quote
{
    /**
     * @param  list<Adjustment>  $adjustments
     */
    private function __construct(
        public Money $base,
        public array $adjustments,
        public Money $total,
    ) {}

    public static function startingAt(Money $base): self
    {
        return new self($base, [], $base);
    }

    public function with(Adjustment $adjustment): self
    {
        if ($adjustment->amount->isZero()) {
            return $this;
        }

        $total = match ($adjustment->kind) {
            AdjustmentKind::Surcharge => $this->total->add($adjustment->amount),
            AdjustmentKind::Discount => $this->total->subtract($adjustment->amount),
        };

        return new self($this->base, [...$this->adjustments, $adjustment], $total);
    }
}
