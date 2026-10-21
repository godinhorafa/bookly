<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\Exception\InvalidCoupon;

final class InMemoryCouponRepository implements CouponRepository
{
    /** @var array<string, Coupon> */
    private array $coupons = [];

    public function get(string $code): Coupon
    {
        return $this->coupons[$code] ?? throw InvalidCoupon::notFound($code);
    }

    public function save(Coupon $coupon): void
    {
        $this->coupons[$coupon->code] = $coupon;
    }
}
