<?php

declare(strict_types=1);

namespace Domain\Pricing\Exception;

use Domain\Shared\Currency;
use Domain\Shared\Exception\DomainException;

final class InvalidCoupon extends DomainException
{
    public static function malformedCode(string $code): self
    {
        return new self("\"{$code}\" is not a valid coupon code.");
    }

    public static function notFound(string $code): self
    {
        return new self("Coupon {$code} does not exist.");
    }

    public static function expired(string $code): self
    {
        return new self("Coupon {$code} has expired.");
    }

    public static function invalidPercentage(int $value): self
    {
        return new self("A percentage coupon must be between 1 and 100, got {$value}.");
    }

    public static function invalidFixedAmount(int $value): self
    {
        return new self("A fixed coupon must be worth at least 1 minor unit, got {$value}.");
    }

    public static function missingCurrency(): self
    {
        return new self('A fixed-amount coupon needs a currency.');
    }

    public static function currencyMismatch(string $code, Currency $coupon, Currency $price): self
    {
        return new self("Coupon {$code} is in {$coupon->value} but the price is in {$price->value}.");
    }
}
