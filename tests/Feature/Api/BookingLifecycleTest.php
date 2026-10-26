<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Cancellation\CancellationPolicyType;
use Domain\Shared\Clock;
use Domain\Space\SpaceRepository;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;

beforeEach(function (): void {
    app()->instance(Clock::class, $this->clock = new FrozenClock('2026-11-01T12:00:00Z'));
    $this->space = Builders::space(hourlyRate: 8_000, policy: CancellationPolicyType::Moderate);
    app(SpaceRepository::class)->save($this->space);
    Sanctum::actingAs($this->user = User::factory()->create());
});

function createBooking(string $start = '2026-11-04T10:00:00-03:00', string $end = '2026-11-04T11:00:00-03:00'): string
{
    return test()->postJson('/api/bookings', ['space_id' => test()->space->id->value, 'start' => $start, 'end' => $end])
        ->assertCreated()
        ->json('data.id');
}

it('confirms a booking once it is paid', function (): void {
    $id = createBooking();

    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_card_visa'])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->getJson("/api/bookings/{$id}")->assertJsonPath('data.status', 'confirmed');
});

it('answers 402 with the reason when the card is declined, and keeps the booking pending', function (): void {
    $id = createBooking();

    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_card_visa_chargeDeclinedInsufficientFunds'])
        ->assertStatus(402)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('decline_reason', 'insufficient_funds');

    $this->getJson("/api/bookings/{$id}")->assertJsonPath('data.status', 'pending');
});

it('tells the customer until when an unpaid booking is held', function (): void {
    $id = createBooking();

    $this->getJson("/api/bookings/{$id}")
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.hold_until', '2026-11-01T12:15:00+00:00');

    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_card_visa'])
        ->assertOk()
        ->assertJsonMissingPath('data.hold_until');
});

it('refuses payment after the hold, then expires the booking and frees the slot', function (): void {
    $id = createBooking();
    $this->clock->travelTo('2026-11-01T12:15:00Z');

    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_card_visa'])
        ->assertConflict()
        ->assertJsonPath('type', 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#invalid-state');

    $this->clock->travelTo('2026-11-01T12:20:00Z');
    $this->artisan('bookings:expire')->expectsOutput('expired: 1')->assertSuccessful();

    $this->getJson("/api/bookings/{$id}")->assertJsonPath('data.status', 'expired');
    createBooking(); // the same slot is free again
});

it('refuses to pay twice', function (): void {
    $id = createBooking();
    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_card_visa'])->assertOk();

    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_card_visa'])
        ->assertConflict()
        ->assertJsonPath('type', 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#invalid-state');
});

it('cancels with a refund that follows the space policy', function (): void {
    $id = createBooking(); // starts 2026-11-04T13:00Z
    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_card_visa'])->assertOk();
    $this->clock->travelTo('2026-11-03T01:00:00Z'); // 36h before: moderate gives 50%

    $this->postJson("/api/bookings/{$id}/cancellation")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation.refund.amount', 4_000);
});

it('lists only my bookings, latest first', function (): void {
    $early = createBooking();
    $late = createBooking('2026-11-05T10:00:00-03:00', '2026-11-05T11:00:00-03:00');

    Sanctum::actingAs(User::factory()->create());
    createBooking('2026-11-06T10:00:00-03:00', '2026-11-06T11:00:00-03:00');

    Sanctum::actingAs($this->user);
    $this->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $late)
        ->assertJsonPath('data.1.id', $early);
});

it('hides other customers\' bookings behind a 404', function (): void {
    $id = createBooking();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/bookings/{$id}")->assertNotFound();
    $this->postJson("/api/bookings/{$id}/cancellation")->assertNotFound();
});

it('rejects malformed ids as invalid requests', function (): void {
    $this->getJson('/api/bookings/not-a-uuid')
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'BookingId must be a UUID, got "not-a-uuid".');
});
