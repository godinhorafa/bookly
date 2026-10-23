<?php

declare(strict_types=1);

use App\Models\User;

it('issues a bearer token for valid credentials', function (): void {
    User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret-password']);

    $token = $this->postJson('/api/tokens', ['email' => 'ana@example.com', 'password' => 'secret-password', 'device_name' => 'tests'])
        ->assertCreated()
        ->json('token');

    $this->withToken($token)->getJson('/api/me')
        ->assertOk()
        ->assertJson(['email' => 'ana@example.com']);
});

it('rejects wrong credentials with a problem response', function (): void {
    User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret-password']);

    $this->postJson('/api/tokens', ['email' => 'ana@example.com', 'password' => 'nope', 'device_name' => 'tests'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Invalid credentials');
});

it('requires a token on protected endpoints', function (): void {
    $this->getJson('/api/me')
        ->assertUnauthorized()
        ->assertJsonPath('type', 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#unauthenticated');
});

it('reports malformed bodies as validation problems', function (): void {
    $this->postJson('/api/tokens', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#validation-failed')
        ->assertJsonStructure(['errors' => ['email', 'password', 'device_name']]);
});
