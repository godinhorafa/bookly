<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence model only: business rules live in Domain\Payment\Payment.
 *
 * @property string $id
 * @property string $booking_id
 * @property int $attempt
 * @property int $amount
 * @property int $refunded_amount
 * @property string $currency
 * @property string $gateway
 * @property string|null $reference
 * @property string|null $refund_reference
 * @property string $status
 * @property string|null $decline_reason
 */
#[Unguarded]
#[Table(name: 'payments', keyType: 'string')]
#[WithoutIncrementing]
final class PaymentModel extends Model
{
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'amount' => 'integer',
            'refunded_amount' => 'integer',
        ];
    }
}
