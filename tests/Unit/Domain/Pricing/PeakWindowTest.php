<?php

declare(strict_types=1);

use Domain\Booking\TimeSlot;
use Domain\Pricing\Exception\InvalidPricingRule;
use Domain\Pricing\Rule\PeakWindow;

$saoPaulo = new DateTimeZone('America/Sao_Paulo'); // UTC-3, no DST

it('counts only the minutes inside the window, in the space time zone', function () use ($saoPaulo): void {
    // Monday 2026-11-02, 17:00-19:30 local
    $slot = TimeSlot::fromStrings('2026-11-02T17:00:00-03:00', '2026-11-02T19:30:00-03:00');

    expect(PeakWindow::weekdays('18:00', '22:00')->minutesWithin($slot, $saoPaulo))->toBe(90);
});

it('ignores days the window does not apply to', function () use ($saoPaulo): void {
    // Sunday 2026-11-01
    $slot = TimeSlot::fromStrings('2026-11-01T18:00:00-03:00', '2026-11-01T20:00:00-03:00');

    expect(PeakWindow::weekdays('18:00', '22:00')->minutesWithin($slot, $saoPaulo))->toBe(0);
});

it('handles bookings that cross midnight into another kind of day', function () use ($saoPaulo): void {
    // Friday 21:00 -> Saturday 10:00 local
    $slot = TimeSlot::fromStrings('2026-11-06T21:00:00-03:00', '2026-11-07T10:00:00-03:00');

    expect(PeakWindow::weekdays('18:00', '22:00')->minutesWithin($slot, $saoPaulo))->toBe(60)
        ->and(PeakWindow::weekends('09:00', '13:00')->minutesWithin($slot, $saoPaulo))->toBe(60);
});

it('follows wall-clock time across a DST change', function (): void {
    // Lisbon leaves summer time on Sunday 2026-10-25 (UTC+1 -> UTC+0).
    $lisbon = new DateTimeZone('Europe/Lisbon');
    $window = PeakWindow::weekends('09:00', '13:00');

    $saturday = TimeSlot::fromStrings('2026-10-24T08:00:00Z', '2026-10-24T12:00:00Z'); // 09:00-13:00 local
    $sunday = TimeSlot::fromStrings('2026-10-25T08:00:00Z', '2026-10-25T12:00:00Z');   // 08:00-12:00 local

    expect($window->minutesWithin($saturday, $lisbon))->toBe(240)
        ->and($window->minutesWithin($sunday, $lisbon))->toBe(180);
});

it('accepts 24:00 as the end of the day', function () use ($saoPaulo): void {
    $slot = TimeSlot::fromStrings('2026-11-02T23:00:00-03:00', '2026-11-03T01:00:00-03:00');

    expect(PeakWindow::weekdays('20:00', '24:00')->minutesWithin($slot, $saoPaulo))->toBe(60);
});

it('rejects invalid windows', function (Closure $build): void {
    $build();
})->with([
    'hour 25' => [fn (): PeakWindow => PeakWindow::weekdays('18:00', '25:00')],
    'missing zero padding' => [fn (): PeakWindow => PeakWindow::weekdays('9:00', '12:00')],
    'past midnight' => [fn (): PeakWindow => PeakWindow::weekdays('24:30', '24:45')],
    'end before start' => [fn (): PeakWindow => PeakWindow::weekdays('22:00', '18:00')],
    'empty' => [fn (): PeakWindow => PeakWindow::weekdays('18:00', '18:00')],
    'no weekdays' => [fn (): PeakWindow => new PeakWindow([], '18:00', '22:00')],
    'weekday 8' => [fn (): PeakWindow => new PeakWindow([8], '18:00', '22:00')],
])->throws(InvalidPricingRule::class);
