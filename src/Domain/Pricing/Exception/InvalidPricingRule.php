<?php

declare(strict_types=1);

namespace Domain\Pricing\Exception;

use Domain\Shared\Exception\DomainException;

final class InvalidPricingRule extends DomainException
{
    public static function percentOutOfRange(int $percent): self
    {
        return new self("Pricing percentage must be between 1 and 100, got {$percent}.");
    }

    public static function invalidMinimumDuration(int $minutes): self
    {
        return new self("Minimum duration must be positive, got {$minutes} minutes.");
    }
}
