<?php

declare(strict_types=1);

use Domain\Booking\TimeSlot;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Space\Exception\InvalidSpace;
use Domain\Space\Space;
use Domain\Space\SpaceId;

function aSpace(string $name = 'Room', string $timezone = 'America/Sao_Paulo', int $capacity = 4, int $rate = 6_000): Space
{
    return new Space(SpaceId::generate(), $name, $timezone, $capacity, Money::of($rate, Currency::BRL));
}

it('trims the name', function (): void {
    expect(aSpace(name: '  Studio A  ')->name)->toBe('Studio A');
});

it('validates its invariants', function (Closure $build): void {
    $build();
})->with([
    'blank name' => [fn (): Space => aSpace(name: '   ')],
    'zero capacity' => [fn (): Space => aSpace(capacity: 0)],
    'unknown time zone' => [fn (): Space => aSpace(timezone: 'Mars/Olympus_Mons')],
])->throws(InvalidSpace::class);

it('prorates the base price by the minute', function (string $end, int $expected): void {
    $slot = TimeSlot::fromStrings('2026-11-02T10:00:00Z', $end);

    expect(aSpace(rate: 6_000)->basePriceFor($slot)->amount)->toBe($expected);
})->with([
    '1 hour' => ['2026-11-02T11:00:00Z', 6_000],
    '90 minutes' => ['2026-11-02T11:30:00Z', 9_000],
    '15 minutes' => ['2026-11-02T10:15:00Z', 1_500],
    '1 minute' => ['2026-11-02T10:01:00Z', 100],
]);

it('rounds prorated prices half up', function (): void {
    $slot = TimeSlot::fromStrings('2026-11-02T10:00:00Z', '2026-11-02T10:01:00Z');

    // 1999 / 60 = 33.316... -> 33
    expect(aSpace(rate: 1_999)->basePriceFor($slot)->amount)->toBe(33);
});
