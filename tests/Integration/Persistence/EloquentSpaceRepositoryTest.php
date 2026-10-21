<?php

declare(strict_types=1);

use Domain\Cancellation\CancellationPolicyType;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Space\Exception\SpaceNotFound;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;

it('persists a space and reads it back', function (): void {
    $repository = app(SpaceRepository::class);
    $space = new Space(SpaceId::generate(), 'Rooftop desk', 'Europe/Lisbon', 2, Money::of(1_250, Currency::EUR));

    $repository->save($space);
    $found = $repository->get($space->id);

    expect($found->name)->toBe('Rooftop desk')
        ->and($found->timezone->getName())->toBe('Europe/Lisbon')
        ->and($found->capacity)->toBe(2)
        ->and($found->hourlyRate->equals(Money::of(1_250, Currency::EUR)))->toBeTrue();
});

it('throws when the space does not exist', function (): void {
    app(SpaceRepository::class)->get(SpaceId::generate());
})->throws(SpaceNotFound::class);

it('persists the cancellation policy', function (): void {
    $repository = app(SpaceRepository::class);
    $space = new Space(SpaceId::generate(), 'Court 3', 'America/Sao_Paulo', 4, Money::of(9_000, Currency::BRL), CancellationPolicyType::Strict);

    $repository->save($space);

    expect($repository->get($space->id)->cancellationPolicy)->toBe(CancellationPolicyType::Strict);
});
