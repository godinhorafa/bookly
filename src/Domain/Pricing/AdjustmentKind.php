<?php

declare(strict_types=1);

namespace Domain\Pricing;

enum AdjustmentKind: string
{
    case Surcharge = 'surcharge';
    case Discount = 'discount';
}
