<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Shared\Clock;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;

/*
 * The HTTP contract of POST /api/bookings. These tests only look at the API
 * (status codes, response body, what ends up in the database), so they stay
 * unchanged when the implementation behind the endpoint is refactored.
 */

beforeEach(function (): void {
    // Monday 2026-11-02 is tomorrow; "now" is 09:00 in São Paulo.
    Carbon::setTestNow('2026-11-01T12:00:00Z');
    app()->instance(Clock::class, new FrozenClock('2026-11-01T12:00:00Z'));

    $this->space = Builders::space(hourlyRate: 8_000);
    app(SpaceRepository::class)->save($this->space);

    Sanctum::actingAs($this->user = User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'space_id' => test()->space->id->value,
        'start' => '2026-11-03T10:00:00-03:00',
        'end' => '2026-11-03T11:30:00-03:00',
    ], $overrides);
}

it('creates a pending booking priced from the space rate', function (): void {
    $response = $this->postJson('/api/bookings', bookingPayload())->assertCreated();

    $response->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.space_id', $this->space->id->value)
        ->assertJsonPath('data.price.amount', 12_000) // 1h30 at BRL 80.00/h
        ->assertJsonPath('data.price.currency', 'BRL');

    expect(DB::table('bookings')->where('id', $response->json('data.id'))->value('user_id'))->toBe($this->user->id);
});

it('refuses a slot that overlaps another booking', function (): void {
    $this->postJson('/api/bookings', bookingPayload())->assertCreated();

    $this->postJson('/api/bookings', bookingPayload(['start' => '2026-11-03T11:00:00-03:00', 'end' => '2026-11-03T12:00:00-03:00']))
        ->assertConflict();

    expect(DB::table('bookings')->count())->toBe(1);
});

it('refuses bookings that start too soon', function (): void {
    $this->postJson('/api/bookings', bookingPayload(['start' => '2026-11-01T09:30:00-03:00', 'end' => '2026-11-01T10:30:00-03:00']))
        ->assertUnprocessable();

    expect(DB::table('bookings')->count())->toBe(0);
});

it('refuses bookings that are too short', function (): void {
    $this->postJson('/api/bookings', bookingPayload(['end' => '2026-11-03T10:15:00-03:00']))
        ->assertUnprocessable();
});

it('validates the request body', function (): void {
    $this->postJson('/api/bookings', ['start' => 'whenever'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['space_id', 'start', 'end']);
});

it('answers 404 for an unknown space', function (): void {
    $this->postJson('/api/bookings', bookingPayload(['space_id' => SpaceId::generate()->value]))
        ->assertNotFound();
});

it('requires authentication', function (): void {
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', '')->postJson('/api/bookings', bookingPayload())->assertUnauthorized();
});
