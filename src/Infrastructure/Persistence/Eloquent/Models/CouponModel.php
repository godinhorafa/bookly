<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence model only: business rules live in Domain\Pricing\Coupon.
 *
 * @property string $code
 * @property string $discount_type
 * @property int $value
 * @property string|null $currency
 * @property string|null $expires_at timestamptz, see Timestamptz
 */
#[Unguarded]
#[Table(name: 'coupons', key: 'code', keyType: 'string')]
#[WithoutIncrementing]
final class CouponModel extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
