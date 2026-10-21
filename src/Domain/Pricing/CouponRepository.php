<?php

declare(strict_types=1);

namespace Domain\Pricing;

use Domain\Pricing\Exception\InvalidCoupon;

interface CouponRepository
{
    /**
     * @throws InvalidCoupon when no coupon has this code
     */
    public function get(string $code): Coupon;

    public function save(Coupon $coupon): void;
}
