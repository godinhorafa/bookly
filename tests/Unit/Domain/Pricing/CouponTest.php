<?php

declare(strict_types=1);

use Domain\Pricing\Coupon;
use Domain\Pricing\Exception\InvalidCoupon;
use Domain\Shared\Currency;
use Domain\Shared\Money;

it('normalises codes typed by customers', function (): void {
    expect(Coupon::percentage('  summer-10 ', 10)->code)->toBe('SUMMER-10');
});

it('rejects malformed codes', function (string $code): void {
    Coupon::normaliseCode($code);
})->with(['ab', 'has space', 'emoji🎉', str_repeat('A', 33)])->throws(InvalidCoupon::class);

it('computes percentage and fixed discounts', function (): void {
    $subtotal = Money::of(8_000, Currency::BRL);

    expect(Coupon::percentage('TENOFF', 10)->discountOn($subtotal)->amount)->toBe(800)
        ->and(Coupon::fixed('MINUS20', Money::of(2_000, Currency::BRL))->discountOn($subtotal)->amount)->toBe(2_000)
        ->and(Coupon::fixed('MINUS200', Money::of(20_000, Currency::BRL))->discountOn($subtotal)->amount)->toBe(8_000);
});

it('refuses a fixed coupon in another currency', function (): void {
    Coupon::fixed('EURO5', Money::of(500, Currency::EUR))->discountOn(Money::of(8_000, Currency::BRL));
})->throws(InvalidCoupon::class, 'Coupon EURO5 is in EUR but the price is in BRL.');

it('expires at the exact expiry instant', function (): void {
    $coupon = Coupon::percentage('BLACKFRIDAY', 30, new DateTimeImmutable('2026-11-28T03:00:00Z'));

    $coupon->assertUsableAt(new DateTimeImmutable('2026-11-28T02:59:59Z'));

    expect(fn () => $coupon->assertUsableAt(new DateTimeImmutable('2026-11-28T03:00:00Z')))
        ->toThrow(InvalidCoupon::class, 'Coupon BLACKFRIDAY has expired.');
});

it('validates coupon values', function (Closure $build): void {
    $build();
})->with([
    '0%' => [fn (): Coupon => Coupon::percentage('ZERO', 0)],
    '101%' => [fn (): Coupon => Coupon::percentage('TOOMUCH', 101)],
    'zero fixed' => [fn (): Coupon => Coupon::fixed('NOTHING', Money::zero(Currency::BRL))],
])->throws(InvalidCoupon::class);
