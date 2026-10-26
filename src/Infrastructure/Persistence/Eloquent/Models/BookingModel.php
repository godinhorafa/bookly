<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence model only: business rules live in Domain\Booking\Booking.
 *
 * @property string $id
 * @property string $space_id
 * @property int $user_id
 * @property string $status
 * @property int $total_amount
 * @property string $currency
 * @property string $period tstzrange literal, see TstzRange
 * @property string|null $cancelled_at timestamptz, see Timestamptz
 * @property int|null $refund_amount
 * @property string $hold_until timestamptz, see Timestamptz
 */
#[Unguarded]
#[Table(name: 'bookings', keyType: 'string')]
#[WithoutIncrementing]
final class BookingModel extends Model
{
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'total_amount' => 'integer',
            'refund_amount' => 'integer',
        ];
    }
}
