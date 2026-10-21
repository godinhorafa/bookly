<?php

declare(strict_types=1);

use Domain\Cancellation\CancellationPolicyType;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Shared\WeeklyWindow;
use Domain\Space\Exception\SpaceNotFound;
use Domain\Space\OpeningHours;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;
use Illuminate\Support\Facades\DB;

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

it('persists opening hours as jsonb', function (): void {
    $repository = app(SpaceRepository::class);
    $hours = new OpeningHours(WeeklyWindow::weekdays('08:00', '22:00'), WeeklyWindow::weekends('09:00', '24:00'));
    $space = new Space(SpaceId::generate(), 'Studio', 'America/Sao_Paulo', 4, Money::of(9_000, Currency::BRL), openingHours: $hours);

    $repository->save($space);
    $found = $repository->get($space->id)->openingHours;

    expect(array_map(fn (WeeklyWindow $w): array => [$w->weekdays, $w->startTime(), $w->endTime()], $found->windows))->toBe([
        [[1, 2, 3, 4, 5], '08:00', '22:00'],
        [[6, 7], '09:00', '24:00'],
    ]);
});

it('defaults existing spaces to always open', function (): void {
    $repository = app(SpaceRepository::class);
    $space = new Space(SpaceId::generate(), 'Court', 'America/Sao_Paulo', 4, Money::of(9_000, Currency::BRL));
    $repository->save($space);
    DB::table('spaces')->where('id', $space->id->value)->update(['opening_hours' => DB::raw('DEFAULT')]);

    expect($repository->get($space->id)->openingHours->windows[0]->weekdays)->toBe([1, 2, 3, 4, 5, 6, 7]);
});
