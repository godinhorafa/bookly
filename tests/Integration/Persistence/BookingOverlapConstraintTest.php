<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Booking\TimeSlot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Postgres\TstzRange;

const EXCLUSION_VIOLATION = '23P01';

beforeEach(function (): void {
    $this->userId = User::factory()->create()->id;
    $this->spaceId = createSpace();
});

function createSpace(): string
{
    $id = (string) Str::uuid();
    DB::table('spaces')->insert([
        'id' => $id,
        'name' => 'Meeting room ' . Str::random(4),
        'capacity' => 8,
        'hourly_rate_amount' => 5_000,
        'currency' => 'BRL',
    ]);

    return $id;
}

/**
 * Each insert runs in a nested transaction (a SAVEPOINT), so a constraint
 * violation does not abort the outer transaction used by RefreshDatabase.
 */
function book(string $spaceId, int $userId, string $start, string $end, string $status = 'confirmed'): void
{
    DB::transaction(fn () => DB::table('bookings')->insert([
        'id' => (string) Str::uuid(),
        'space_id' => $spaceId,
        'user_id' => $userId,
        'status' => $status,
        'total_amount' => 5_000,
        'currency' => 'BRL',
        'period' => TstzRange::fromTimeSlot(TimeSlot::fromStrings($start, $end)),
        'cancelled_at' => $status === 'cancelled' ? '2026-10-01 00:00:00+00' : null,
        'refund_amount' => $status === 'cancelled' ? 0 : null,
    ]));
}

function expectExclusionViolation(Closure $attempt): void
{
    try {
        $attempt();
    } catch (QueryException $e) {
        expect($e->getCode())->toBe(EXCLUSION_VIOLATION);

        return;
    }

    test()->fail('Expected the exclusion constraint to reject the booking.');
}

it('rejects an overlapping booking for the same space', function (): void {
    book($this->spaceId, $this->userId, '2026-11-02T10:00:00Z', '2026-11-02T12:00:00Z');

    expectExclusionViolation(
        fn () => book($this->spaceId, $this->userId, '2026-11-02T11:00:00Z', '2026-11-02T13:00:00Z'),
    );

    expect(DB::table('bookings')->count())->toBe(1);
});

it('detects overlaps expressed in different time zones', function (): void {
    book($this->spaceId, $this->userId, '2026-11-02T13:00:00Z', '2026-11-02T14:00:00Z');

    expectExclusionViolation(
        fn () => book($this->spaceId, $this->userId, '2026-11-02T10:30:00-03:00', '2026-11-02T11:30:00-03:00'),
    );
});

it('allows back-to-back bookings thanks to half-open ranges', function (): void {
    book($this->spaceId, $this->userId, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
    book($this->spaceId, $this->userId, '2026-11-02T11:00:00Z', '2026-11-02T12:00:00Z');

    expect(DB::table('bookings')->count())->toBe(2);
});

it('allows the same slot in a different space', function (): void {
    book($this->spaceId, $this->userId, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
    book(createSpace(), $this->userId, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');

    expect(DB::table('bookings')->count())->toBe(2);
});

it('frees the slot once a booking is cancelled', function (): void {
    book($this->spaceId, $this->userId, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z', status: 'cancelled');
    book($this->spaceId, $this->userId, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');

    expect(DB::table('bookings')->count())->toBe(2);
});

it('round-trips the period through the TimeSlot value object', function (): void {
    book($this->spaceId, $this->userId, '2026-11-02T10:00:00-03:00', '2026-11-02T11:30:00-03:00');

    $stored = TstzRange::toTimeSlot(DB::table('bookings')->value('period'));

    expect($stored->equals(TimeSlot::fromStrings('2026-11-02T13:00:00Z', '2026-11-02T14:30:00Z')))->toBeTrue();
});

it('rejects inclusive upper bounds at the schema level', function (): void {
    expect(fn () => DB::transaction(fn () => DB::table('bookings')->insert([
        'id' => (string) Str::uuid(),
        'space_id' => $this->spaceId,
        'user_id' => $this->userId,
        'status' => 'confirmed',
        'total_amount' => 5_000,
        'currency' => 'BRL',
        'period' => '[2026-11-02 10:00:00+00,2026-11-02 11:00:00+00]',
    ])))->toThrow(QueryException::class, 'bookings_period_half_open');
});
