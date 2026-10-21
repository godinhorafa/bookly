<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent;

use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\DiscountType;
use Domain\Pricing\Exception\InvalidCoupon;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Infrastructure\Persistence\Eloquent\Models\CouponModel;
use Infrastructure\Persistence\Postgres\Timestamptz;

final class EloquentCouponRepository implements CouponRepository
{
    public function get(string $code): Coupon
    {
        $model = CouponModel::query()->find($code);

        if (! $model instanceof CouponModel) {
            throw InvalidCoupon::notFound($code);
        }

        $expiresAt = Timestamptz::fromDatabase($model->expires_at);

        return match (DiscountType::from($model->discount_type)) {
            DiscountType::Percentage => Coupon::percentage($model->code, $model->value, $expiresAt),
            DiscountType::Fixed => Coupon::fixed(
                $model->code,
                Money::of($model->value, Currency::from($model->currency ?? throw InvalidCoupon::missingCurrency())),
                $expiresAt,
            ),
        };
    }

    public function save(Coupon $coupon): void
    {
        CouponModel::query()->updateOrCreate(['code' => $coupon->code], [
            'discount_type' => $coupon->type->value,
            'value' => $coupon->value,
            'currency' => $coupon->currency?->value,
            'expires_at' => Timestamptz::toDatabase($coupon->expiresAt),
        ]);
    }
}
