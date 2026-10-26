<?php

declare(strict_types=1);

use Domain\Shared\Exception\InvalidWeeklyWindow;
use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;

$saoPaulo = new DateTimeZone('America/Sao_Paulo'); // UTC-3, no DST

it('counts only the minutes inside the window, in the space time zone', function () use ($saoPaulo): void {
    // Monday 2026-11-02, 17:00-19:30 local
    $slot = TimeSlot::fromStrings('2026-11-02T17:00:00-03:00', '2026-11-02T19:30:00-03:00');

    expect(WeeklyWindow::weekdays('18:00', '22:00')->minutesWithin($slot, $saoPaulo))->toBe(90);
});

it('ignores days the window does not apply to', function () use ($saoPaulo): void {
    // Sunday 2026-11-01
    $slot = TimeSlot::fromStrings('2026-11-01T18:00:00-03:00', '2026-11-01T20:00:00-03:00');

    expect(WeeklyWindow::weekdays('18:00', '22:00')->minutesWithin($slot, $saoPaulo))->toBe(0);
});

it('handles bookings that cross midnight into another kind of day', function () use ($saoPaulo): void {
    // Friday 21:00 -> Saturday 10:00 local
    $slot = TimeSlot::fromStrings('2026-11-06T21:00:00-03:00', '2026-11-07T10:00:00-03:00');

    expect(WeeklyWindow::weekdays('18:00', '22:00')->minutesWithin($slot, $saoPaulo))->toBe(60)
        ->and(WeeklyWindow::weekends('09:00', '13:00')->minutesWithin($slot, $saoPaulo))->toBe(60);
});

it('follows wall-clock time across a DST change', function (): void {
    // Lisbon leaves summer time on Sunday 2026-10-25 (UTC+1 -> UTC+0).
    $lisbon = new DateTimeZone('Europe/Lisbon');
    $window = WeeklyWindow::weekends('09:00', '13:00');

    $saturday = TimeSlot::fromStrings('2026-10-24T08:00:00Z', '2026-10-24T12:00:00Z'); // 09:00-13:00 local
    $sunday = TimeSlot::fromStrings('2026-10-25T08:00:00Z', '2026-10-25T12:00:00Z');   // 08:00-12:00 local

    expect($window->minutesWithin($saturday, $lisbon))->toBe(240)
        ->and($window->minutesWithin($sunday, $lisbon))->toBe(180);
});

it('accepts 24:00 as the end of the day', function () use ($saoPaulo): void {
    $slot = TimeSlot::fromStrings('2026-11-02T23:00:00-03:00', '2026-11-03T01:00:00-03:00');

    expect(WeeklyWindow::weekdays('20:00', '24:00')->minutesWithin($slot, $saoPaulo))->toBe(60);
});

it('rejects invalid windows', function (Closure $build): void {
    $build();
})->with([
    'hour 25' => [fn (): WeeklyWindow => WeeklyWindow::weekdays('18:00', '25:00')],
    'missing zero padding' => [fn (): WeeklyWindow => WeeklyWindow::weekdays('9:00', '12:00')],
    'past midnight' => [fn (): WeeklyWindow => WeeklyWindow::weekdays('24:30', '24:45')],
    'end before start' => [fn (): WeeklyWindow => WeeklyWindow::weekdays('22:00', '18:00')],
    'empty' => [fn (): WeeklyWindow => WeeklyWindow::weekdays('18:00', '18:00')],
    'no weekdays' => [fn (): WeeklyWindow => new WeeklyWindow([], '18:00', '22:00')],
    'weekday 0' => [fn (): WeeklyWindow => new WeeklyWindow([0], '18:00', '22:00')],
    'weekday 8' => [fn (): WeeklyWindow => new WeeklyWindow([8], '18:00', '22:00')],
])->throws(InvalidWeeklyWindow::class);

it('formats its bounds as HH:MM', function (): void {
    $window = WeeklyWindow::weekdays('11:50', '24:00');

    expect($window->startTime())->toBe('11:50')
        ->and($window->endTime())->toBe('24:00');
});

it('overlaps only when sharing a weekday and some minutes', function (): void {
    $weekdayMornings = WeeklyWindow::weekdays('08:00', '12:00');

    expect($weekdayMornings->overlaps(new WeeklyWindow([5, 6], '11:00', '13:00')))->toBeTrue()
        ->and($weekdayMornings->overlaps(WeeklyWindow::weekdays('12:00', '14:00')))->toBeFalse()
        ->and(WeeklyWindow::weekdays('12:00', '14:00')->overlaps($weekdayMornings))->toBeFalse()
        ->and($weekdayMornings->overlaps(WeeklyWindow::weekends('08:00', '12:00')))->toBeFalse();
});

it('names the weekdays of its shortcuts, without duplicates', function (): void {
    expect(WeeklyWindow::weekdays('08:00', '12:00')->weekdays)->toBe([1, 2, 3, 4, 5])
        ->and(WeeklyWindow::weekends('08:00', '12:00')->weekdays)->toBe([6, 7])
        ->and((new WeeklyWindow([3, 3, 1], '08:00', '12:00'))->weekdays)->toBe([3, 1]);
});

it('places windows at the exact minute', function () use ($saoPaulo): void {
    // Monday 11:00-13:00 local against a 11:50-12:10 window
    $slot = TimeSlot::fromStrings('2026-11-02T11:00:00-03:00', '2026-11-02T13:00:00-03:00');

    expect(WeeklyWindow::weekdays('11:50', '12:10')->minutesWithin($slot, $saoPaulo))->toBe(20);
});
