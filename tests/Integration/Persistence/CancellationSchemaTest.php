<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Space\SpaceRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Builders;

/*
 * The database double-checks the invariants the domain already enforces,
 * so a bug or a manual UPDATE can't leave a booking in an impossible state.
 */

function insertBooking(array $overrides): void
{
    $space = Builders::space();
    app(SpaceRepository::class)->save($space);

    DB::transaction(fn () => DB::table('bookings')->insert(array_merge([
        'id' => (string) Str::uuid(),
        'space_id' => $space->id->value,
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'total_amount' => 5_000,
        'currency' => 'BRL',
        'period' => '[2026-11-02 10:00:00+00,2026-11-02 11:00:00+00)',
        'hold_until' => '2026-11-01 12:15:00+00',
    ], $overrides)));
}

it('rejects inconsistent cancellation data', function (array $row, string $constraint): void {
    expect(fn () => insertBooking($row))->toThrow(QueryException::class, $constraint);
})->with([
    'cancelled without details' => [['status' => 'cancelled'], 'bookings_cancellation_consistency'],
    'active with a refund' => [['cancelled_at' => '2026-10-01 00:00:00+00', 'refund_amount' => 0], 'bookings_cancellation_consistency'],
    'refund above price' => [['status' => 'cancelled', 'cancelled_at' => '2026-10-01 00:00:00+00', 'refund_amount' => 5_001], 'bookings_refund_not_above_price'],
]);

it('rejects unknown cancellation policies on spaces', function (): void {
    $space = Builders::space();
    app(SpaceRepository::class)->save($space);

    expect(fn () => DB::transaction(fn () => DB::table('spaces')->where('id', $space->id->value)->update(['cancellation_policy' => 'whenever'])))
        ->toThrow(QueryException::class, 'spaces_cancellation_policy_check');
});
