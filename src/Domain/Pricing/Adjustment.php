<?php

declare(strict_types=1);

namespace Domain\Pricing;

use Domain\Shared\Money;

/**
 * One line of a price breakdown, e.g. "Peak hours (+20% on 90 min): BRL 18.00".
 */
final readonly class Adjustment
{
    public function __construct(
        public string $rule,
        public string $description,
        public AdjustmentKind $kind,
        public Money $amount,
    ) {}
}
