<?php

declare(strict_types=1);

use Domain\Shared\Currency;
use Domain\Shared\Exception\InvalidMoney;
use Domain\Shared\Money;

it('is created from minor units', function (): void {
    $money = Money::of(15_050, Currency::BRL);

    expect($money->amount)->toBe(15_050)
        ->and($money->currency)->toBe(Currency::BRL)
        ->and($money->format())->toBe('BRL 150.50');
});

it('rejects negative amounts', function (): void {
    Money::of(-1, Currency::BRL);
})->throws(InvalidMoney::class);

it('adds amounts of the same currency', function (): void {
    $sum = Money::of(1_000, Currency::BRL)->add(Money::of(250, Currency::BRL));

    expect($sum->equals(Money::of(1_250, Currency::BRL)))->toBeTrue();
});

it('refuses to mix currencies', function (Closure $operation): void {
    $operation(Money::of(1_000, Currency::BRL), Money::of(1_000, Currency::USD));
})->with([
    'add' => [fn (Money $a, Money $b): Money => $a->add($b)],
    'subtract' => [fn (Money $a, Money $b): Money => $a->subtract($b)],
    'compare' => [fn (Money $a, Money $b): bool => $a->greaterThan($b)],
])->throws(InvalidMoney::class, 'Cannot operate on BRL and USD.');

it('clamps subtraction at zero so a discount never produces a negative price', function (): void {
    $price = Money::of(1_000, Currency::BRL)->subtract(Money::of(1_500, Currency::BRL));

    expect($price->isZero())->toBeTrue();
});

it('multiplies by an integer factor', function (): void {
    expect(Money::of(4_000, Currency::BRL)->multiply(3)->amount)->toBe(12_000);
});

it('computes percentages rounding half up', function (int $amount, int $percent, int $expected): void {
    expect(Money::of($amount, Currency::BRL)->percentage($percent)->amount)->toBe($expected);
})->with([
    'full refund' => [10_000, 100, 10_000],
    'half refund' => [10_000, 50, 5_000],
    'no refund' => [10_000, 0, 0],
    'rounds .5 up' => [1, 50, 1],
    'rounds below .5 down' => [33, 10, 3],
    'odd amount' => [999, 50, 500],
]);

it('rejects percentages outside 0..100', function (int $percent): void {
    Money::of(1_000, Currency::BRL)->percentage($percent);
})->with([-1, 101])->throws(InvalidMoney::class, 'Percentage must be between 0 and 100');

it('compares amounts', function (): void {
    $ten = Money::of(1_000, Currency::BRL);
    $five = Money::of(500, Currency::BRL);

    expect($ten->greaterThan($five))->toBeTrue()
        ->and($five->greaterThan($ten))->toBeFalse()
        ->and($ten->equals(Money::of(1_000, Currency::USD)))->toBeFalse();
});

it('is immutable', function (): void {
    $original = Money::of(1_000, Currency::BRL);
    $original->add(Money::of(1, Currency::BRL));

    expect($original->amount)->toBe(1_000);
});

it('multiplies by a ratio rounding half up', function (int $amount, int $numerator, int $denominator, int $expected): void {
    expect(Money::of($amount, Currency::BRL)->ratio($numerator, $denominator)->amount)->toBe($expected);
})->with([
    '90 of 60 minutes' => [6_000, 90, 60, 9_000],
    'exact half rounds up' => [1, 1, 2, 1],
    'below half rounds down' => [1, 1, 3, 0],
    'odd denominator' => [10, 2, 3, 7],
    'zero numerator' => [5_000, 0, 60, 0],
    'denominator of one' => [700, 3, 1, 2_100],
]);

it('rejects invalid ratios', function (int $numerator, int $denominator): void {
    Money::of(1_000, Currency::BRL)->ratio($numerator, $denominator);
})->with([
    'negative numerator' => [-1, 60],
    'zero denominator' => [1, 0],
])->throws(InvalidMoney::class, 'Invalid ratio');
