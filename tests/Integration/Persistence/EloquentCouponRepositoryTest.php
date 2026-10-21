<?php

declare(strict_types=1);

use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\DiscountType;
use Domain\Pricing\Exception\InvalidCoupon;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('round-trips percentage and fixed coupons', function (): void {
    $repository = app(CouponRepository::class);
    $repository->save(Coupon::percentage('WELCOME10', 10, new DateTimeImmutable('2026-12-31T23:59:59-03:00')));
    $repository->save(Coupon::fixed('MINUS20', Money::of(2_000, Currency::BRL)));

    $percentage = $repository->get('WELCOME10');
    $fixed = $repository->get('MINUS20');

    expect($percentage->type)->toBe(DiscountType::Percentage)
        ->and($percentage->value)->toBe(10)
        ->and($percentage->expiresAt?->format(DATE_ATOM))->toBe('2027-01-01T02:59:59+00:00')
        ->and($fixed->type)->toBe(DiscountType::Fixed)
        ->and($fixed->currency)->toBe(Currency::BRL)
        ->and($fixed->expiresAt)->toBeNull();
});

it('throws for unknown codes', function (): void {
    app(CouponRepository::class)->get('NOPE123');
})->throws(InvalidCoupon::class, 'Coupon NOPE123 does not exist.');

it('enforces coupon rules in the schema too', function (array $row): void {
    expect(fn () => DB::transaction(fn () => DB::table('coupons')->insert(array_merge(['code' => 'BROKEN'], $row))))
        ->toThrow(QueryException::class);
})->with([
    'percentage over 100' => [['discount_type' => 'percentage', 'value' => 150]],
    'fixed without currency' => [['discount_type' => 'fixed', 'value' => 500]],
    'percentage with currency' => [['discount_type' => 'percentage', 'value' => 10, 'currency' => 'BRL']],
    'unknown type' => [['discount_type' => 'bogo', 'value' => 1]],
]);
