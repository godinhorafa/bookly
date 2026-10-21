<?php

declare(strict_types=1);

use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;
use Domain\Space\Exception\InvalidSpace;
use Domain\Space\OpeningHours;

$saoPaulo = new DateTimeZone('America/Sao_Paulo');

it('is always open by default, including overnight', function () use ($saoPaulo): void {
    $overnight = TimeSlot::fromStrings('2026-11-02T22:00:00-03:00', '2026-11-03T06:00:00-03:00');

    expect(OpeningHours::alwaysOpen()->covers($overnight, $saoPaulo))->toBeTrue();
});

it('covers a slot only when every minute is inside a window', function () use ($saoPaulo): void {
    $hours = new OpeningHours(WeeklyWindow::weekdays('08:00', '12:00'), WeeklyWindow::weekdays('13:00', '18:00'));

    expect($hours->covers(TimeSlot::fromStrings('2026-11-02T09:00:00-03:00', '2026-11-02T11:00:00-03:00'), $saoPaulo))->toBeTrue()
        ->and($hours->covers(TimeSlot::fromStrings('2026-11-02T11:00:00-03:00', '2026-11-02T14:00:00-03:00'), $saoPaulo))->toBeFalse()
        ->and($hours->covers(TimeSlot::fromStrings('2026-11-02T17:00:00-03:00', '2026-11-02T18:00:00-03:00'), $saoPaulo))->toBeTrue();
});

it('accepts a booking spanning two adjacent windows on consecutive days', function () use ($saoPaulo): void {
    // Open Friday until midnight and Saturday from midnight: an overnight booking fits.
    $hours = new OpeningHours(new WeeklyWindow([5], '18:00', '24:00'), new WeeklyWindow([6], '00:00', '04:00'));

    expect($hours->covers(TimeSlot::fromStrings('2026-11-06T23:00:00-03:00', '2026-11-07T02:00:00-03:00'), $saoPaulo))->toBeTrue();
});

it('rejects overlapping windows, which would make coverage ambiguous', function (): void {
    new OpeningHours(WeeklyWindow::weekdays('08:00', '14:00'), new WeeklyWindow([3], '12:00', '20:00'));
})->throws(InvalidSpace::class, 'Opening hours windows must not overlap.');

it('allows windows that only touch or fall on other days', function (): void {
    $hours = new OpeningHours(
        WeeklyWindow::weekdays('08:00', '12:00'),
        WeeklyWindow::weekdays('12:00', '18:00'),
        WeeklyWindow::weekends('08:00', '20:00'),
    );

    expect($hours->windows)->toHaveCount(3);
});
