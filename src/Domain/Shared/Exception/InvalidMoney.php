<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

use Domain\Shared\Currency;

final class InvalidMoney extends DomainException
{
    public static function negativeAmount(int $amount): self
    {
        return new self("Money cannot be negative, got {$amount} minor units.");
    }

    public static function currencyMismatch(Currency $left, Currency $right): self
    {
        return new self("Cannot operate on {$left->value} and {$right->value}.");
    }

    public static function invalidPercentage(int $percent): self
    {
        return new self("Percentage must be between 0 and 100, got {$percent}.");
    }

    public static function invalidRatio(int $numerator, int $denominator): self
    {
        return new self("Invalid ratio {$numerator}/{$denominator}: numerator must be >= 0 and denominator > 0.");
    }
}
