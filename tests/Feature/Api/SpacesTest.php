<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Shared\Clock;
use Domain\Shared\WeeklyWindow;
use Domain\Space\OpeningHours;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;

beforeEach(function (): void {
    app()->instance(Clock::class, new FrozenClock('2026-11-01T12:00:00Z'));
    Sanctum::actingAs(User::factory()->create());
});

it('lists spaces with their rate, policy and opening hours', function (): void {
    app(SpaceRepository::class)->save(Builders::space(hourlyRate: 8_000, name: 'Studio', openingHours: new OpeningHours(WeeklyWindow::weekdays('08:00', '22:00'))));

    $this->getJson('/api/spaces')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Studio')
        ->assertJsonPath('data.0.hourly_rate', ['amount' => 8_000, 'currency' => 'BRL'])
        ->assertJsonPath('data.0.cancellation_policy', 'moderate')
        ->assertJsonPath('data.0.opening_hours.0', ['weekdays' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '22:00']);
});

it('shows busy slots without revealing who booked them', function (): void {
    $space = Builders::space();
    app(SpaceRepository::class)->save($space);
    $this->postJson('/api/bookings', ['space_id' => $space->id->value, 'start' => '2026-11-03T10:00:00-03:00', 'end' => '2026-11-03T11:00:00-03:00'])->assertCreated();

    $response = $this->getJson("/api/spaces/{$space->id->value}/availability?from=2026-11-03T00:00:00-03:00&to=2026-11-04T00:00:00-03:00")
        ->assertOk()
        ->assertJsonPath('data.busy', [['start' => '2026-11-03T13:00:00+00:00', 'end' => '2026-11-03T14:00:00+00:00']]);

    expect($response->json('data.busy.0'))->not->toHaveKeys(['id', 'customer_id', 'user_id']);
});

it('limits the availability range', function (): void {
    $space = Builders::space();
    app(SpaceRepository::class)->save($space);

    $this->getJson("/api/spaces/{$space->id->value}/availability?from=2026-11-01T00:00:00Z&to=2027-03-01T00:00:00Z")
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'A range can span at most 62 days.');
});

it('answers 404 for an unknown space', function (): void {
    $id = SpaceId::generate()->value;

    $this->getJson("/api/spaces/{$id}/availability?from=2026-11-01T00:00:00Z&to=2026-11-02T00:00:00Z")->assertNotFound();
});
