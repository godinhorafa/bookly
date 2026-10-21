<?php

declare(strict_types=1);

namespace Domain\Pricing;

use Domain\Pricing\Exception\InvalidCoupon;
use Domain\Shared\Currency;
use Domain\Shared\Money;

final readonly class Coupon
{
    public string $code;

    private function __construct(
        string $code,
        public DiscountType $type,
        public int $value,
        public ?Currency $currency,
        public ?\DateTimeImmutable $expiresAt,
    ) {
        $this->code = self::normaliseCode($code);
    }

    public static function percentage(string $code, int $percent, ?\DateTimeImmutable $expiresAt = null): self
    {
        if ($percent < 1 || $percent > 100) {
            throw InvalidCoupon::invalidPercentage($percent);
        }

        return new self($code, DiscountType::Percentage, $percent, null, $expiresAt);
    }

    public static function fixed(string $code, Money $amount, ?\DateTimeImmutable $expiresAt = null): self
    {
        if ($amount->amount < 1) {
            throw InvalidCoupon::invalidFixedAmount($amount->amount);
        }

        return new self($code, DiscountType::Fixed, $amount->amount, $amount->currency, $expiresAt);
    }

    /**
     * Upper-cases and validates user input such as " summer10 " -> "SUMMER10".
     */
    public static function normaliseCode(string $code): string
    {
        $normalised = strtoupper(trim($code));

        if (preg_match('/^[A-Z0-9_-]{3,32}$/', $normalised) !== 1) {
            throw InvalidCoupon::malformedCode($code);
        }

        return $normalised;
    }

    public function assertUsableAt(\DateTimeImmutable $now): void
    {
        if ($this->expiresAt instanceof \DateTimeImmutable && $now >= $this->expiresAt) {
            throw InvalidCoupon::expired($this->code);
        }
    }

    /**
     * The discount for a given subtotal; never more than the subtotal itself.
     */
    public function discountOn(Money $subtotal): Money
    {
        if ($this->type === DiscountType::Percentage) {
            return $subtotal->percentage($this->value);
        }

        $currency = $this->currency ?? throw InvalidCoupon::missingCurrency();
        if ($currency !== $subtotal->currency) {
            throw InvalidCoupon::currencyMismatch($this->code, $currency, $subtotal->currency);
        }

        return Money::of(min($this->value, $subtotal->amount), $currency);
    }
}
