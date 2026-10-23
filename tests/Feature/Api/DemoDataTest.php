<?php

declare(strict_types=1);

use Domain\Shared\Clock;
use Tests\Support\FrozenClock;

it('seeds a demo account that can log in and book with the demo coupon', function (): void {
    app()->instance(Clock::class, new FrozenClock('2026-11-01T12:00:00Z'));
    $this->seed();

    $token = $this->postJson('/api/tokens', ['email' => 'demo@bookly.test', 'password' => 'password', 'device_name' => 'demo'])->json('token');
    $spaces = $this->withToken($token)->getJson('/api/spaces')->assertOk()->json('data');

    expect(array_column($spaces, 'name'))->toBe(['Event hall', 'Focus room', 'Tennis court']);

    $this->withToken($token)->postJson('/api/bookings', [
        'space_id' => $spaces[1]['id'],
        'start' => '2026-11-03T10:00:00-03:00',
        'end' => '2026-11-03T11:00:00-03:00',
        'coupon' => 'welcome10',
    ])->assertCreated()->assertJsonPath('data.price.amount', 5_400); // 60.00 - 10%
});
