<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Shared\Clock;
use Domain\Space\SpaceRepository;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;

beforeEach(function (): void {
    app()->instance(Clock::class, new FrozenClock('2026-11-01T12:00:00Z'));
    $this->space = Builders::space();
    app(SpaceRepository::class)->save($this->space);
    Sanctum::actingAs(User::factory()->create());
    $this->body = ['space_id' => $this->space->id->value, 'start' => '2026-11-03T10:00:00-03:00', 'end' => '2026-11-03T11:00:00-03:00'];
});

it('replays the original response instead of booking twice', function (): void {
    $first = $this->postJson('/api/bookings', $this->body, ['Idempotency-Key' => 'k-1'])->assertCreated();
    $retry = $this->postJson('/api/bookings', $this->body, ['Idempotency-Key' => 'k-1'])->assertCreated();

    expect($retry->json('data.id'))->toBe($first->json('data.id'))
        ->and($retry->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and(DB::table('bookings')->count())->toBe(1);
});

it('replays errors too, so a retry gets the same answer', function (): void {
    $this->postJson('/api/bookings', $this->body)->assertCreated(); // someone took the slot

    $this->postJson('/api/bookings', $this->body, ['Idempotency-Key' => 'k-2'])->assertConflict();
    $this->postJson('/api/bookings', $this->body, ['Idempotency-Key' => 'k-2'])->assertConflict()->assertHeader('Idempotent-Replayed', 'true');
});

it('rejects a key reused for a different request', function (): void {
    $this->postJson('/api/bookings', $this->body, ['Idempotency-Key' => 'k-3'])->assertCreated();

    $this->postJson('/api/bookings', [...$this->body, 'end' => '2026-11-03T12:00:00-03:00'], ['Idempotency-Key' => 'k-3'])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#idempotency-conflict');
});

it('scopes keys to the customer', function (): void {
    $this->postJson('/api/bookings', $this->body, ['Idempotency-Key' => 'shared'])->assertCreated();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/bookings', $this->body, ['Idempotency-Key' => 'shared'])->assertConflict(); // a fresh attempt, not a replay
});

it('does not store server errors, so the same key can be retried', function (): void {
    $id = $this->postJson('/api/bookings', $this->body)->json('data.id');

    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_fake_gateway_down'], ['Idempotency-Key' => 'pay-1'])
        ->assertStatus(503)
        ->assertHeader('Retry-After', '30');

    $this->postJson("/api/bookings/{$id}/payment", ['payment_method' => 'pm_fake_gateway_down'], ['Idempotency-Key' => 'pay-1'])
        ->assertStatus(503)
        ->assertHeaderMissing('Idempotent-Replayed');
});
