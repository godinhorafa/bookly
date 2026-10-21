<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence model only: business rules live in Domain\Space\Space.
 *
 * @property string $id
 * @property string $name
 * @property string $timezone
 * @property int $capacity
 * @property int $hourly_rate_amount
 * @property string $currency
 * @property string $cancellation_policy
 * @property string $opening_hours jsonb, see OpeningHoursJson
 */
#[Unguarded]
#[Table(name: 'spaces', keyType: 'string')]
#[WithoutIncrementing]
final class SpaceModel extends Model
{
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'hourly_rate_amount' => 'integer',
        ];
    }
}
