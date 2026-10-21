<?php

declare(strict_types=1);

namespace Domain\Pricing;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
