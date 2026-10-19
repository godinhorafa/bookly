<?php

declare(strict_types=1);

namespace Domain\Shared;

use Domain\Shared\Exception\InvalidMoney;

/**
 * Immutable monetary amount stored in minor units (cents) to avoid float rounding.
 * Prices and refunds are never negative, so the invariant is enforced here.
 */
final readonly class Money
{
    private function __construct(
        public int $amount,
        public Currency $currency,
    ) {
        if ($amount < 0) {
            throw InvalidMoney::negativeAmount($amount);
        }
    }

    public static function of(int $minorUnits, Currency $currency): self
    {
        return new self($minorUnits, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Discounts larger than the price clamp to zero instead of going negative.
     */
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->amount - $other->amount), $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * Returns the given share of this amount, rounding half up to the nearest minor unit.
     */
    public function percentage(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw InvalidMoney::invalidPercentage($percent);
        }

        return $this->ratio($percent, 100);
    }

    /**
     * Multiplies by numerator/denominator, rounding half up, e.g. ratio(90, 60) for 90 minutes of an hourly rate.
     */
    public function ratio(int $numerator, int $denominator): self
    {
        if ($numerator < 0 || $denominator <= 0) {
            throw InvalidMoney::invalidRatio($numerator, $denominator);
        }

        return new self(intdiv(2 * $this->amount * $numerator + $denominator, 2 * $denominator), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function format(): string
    {
        $divisor = 10 ** $this->currency->minorUnits();

        return sprintf(
            '%s %d.%0' . $this->currency->minorUnits() . 'd',
            $this->currency->value,
            intdiv($this->amount, $divisor),
            $this->amount % $divisor,
        );
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoney::currencyMismatch($this->currency, $other->currency);
        }
    }
}
