<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Shared\Clock;
use Domain\Shared\WeeklyWindow;
use Domain\Space\OpeningHours;
use Domain\Space\SpaceRepository;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;

/*
 * Behaviour the domain gets right and the original fat controller got wrong
 * (see docs/refactoring/fat-controller.md). Each of these failed before the
 * refactoring and passes after it, without touching CreateBookingTest.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-01T12:00:00Z');
    app()->instance(Clock::class, new FrozenClock('2026-11-01T12:00:00Z'));
    Sanctum::actingAs(User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

it('charges peak hours in the space local time, not in UTC', function (): void {
    $space = Builders::space(hourlyRate: 8_000); // America/Sao_Paulo, UTC-3
    app(SpaceRepository::class)->save($space);

    // 19:00-20:00 in São Paulo is peak (18:00-22:00 local) but 22:00-23:00 UTC.
    $this->postJson('/api/bookings', ['space_id' => $space->id->value, 'start' => '2026-11-03T19:00:00-03:00', 'end' => '2026-11-03T20:00:00-03:00'])
        ->assertCreated()
        ->assertJsonPath('data.price.amount', 10_000); // 80.00 + 25%
});

it('respects the opening hours of the space', function (): void {
    $space = Builders::space(openingHours: new OpeningHours(WeeklyWindow::weekdays('08:00', '18:00')));
    app(SpaceRepository::class)->save($space);

    $this->postJson('/api/bookings', ['space_id' => $space->id->value, 'start' => '2026-11-03T17:00:00-03:00', 'end' => '2026-11-03T19:00:00-03:00'])
        ->assertUnprocessable()
        ->assertJsonPath('violations', ['Bookings must fall entirely within the opening hours of the space.']);
});

it('reports every broken rule at once, as a problem document', function (): void {
    $space = Builders::space();
    app(SpaceRepository::class)->save($space);

    // Starts in 20 minutes, lasts 7 minutes and is off the 15-minute grid.
    $this->postJson('/api/bookings', ['space_id' => $space->id->value, 'start' => '2026-11-01T09:20:00-03:00', 'end' => '2026-11-01T09:27:00-03:00'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#booking-rules-violated')
        ->assertJsonCount(3, 'violations');
});

it('explains the price with an itemised quote', function (): void {
    $space = Builders::space(hourlyRate: 8_000);
    app(SpaceRepository::class)->save($space);

    $this->postJson('/api/bookings', ['space_id' => $space->id->value, 'start' => '2026-11-03T17:00:00-03:00', 'end' => '2026-11-03T19:00:00-03:00'])
        ->assertCreated()
        ->assertJsonPath('data.quote.base.amount', 16_000)
        ->assertJsonPath('data.quote.adjustments.0.rule', 'peak_hours')
        ->assertJsonPath('data.quote.adjustments.0.amount.amount', 2_000)
        ->assertJsonPath('data.price.amount', 18_000);
});
