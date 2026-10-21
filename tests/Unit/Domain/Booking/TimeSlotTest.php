<?php

declare(strict_types=1);

use Domain\Booking\Exception\InvalidTimeSlot;
use Domain\Booking\TimeSlot;

it('normalises both ends to UTC', function (): void {
    $slot = TimeSlot::fromStrings('2026-11-02T10:00:00-03:00', '2026-11-02T11:30:00-03:00');

    expect($slot->start->format(DATE_ATOM))->toBe('2026-11-02T13:00:00+00:00')
        ->and($slot->end->format(DATE_ATOM))->toBe('2026-11-02T14:30:00+00:00')
        ->and($slot->durationInMinutes())->toBe(90);
});

it('rejects slots whose end is not after the start', function (string $start, string $end): void {
    TimeSlot::fromStrings($start, $end);
})->with([
    'zero length' => ['2026-11-02T10:00:00Z', '2026-11-02T10:00:00Z'],
    'reversed' => ['2026-11-02T11:00:00Z', '2026-11-02T10:00:00Z'],
])->throws(InvalidTimeSlot::class);

it('detects overlaps', function (string $otherStart, string $otherEnd, bool $overlaps): void {
    $slot = TimeSlot::fromStrings('2026-11-02T10:00:00Z', '2026-11-02T12:00:00Z');
    $other = TimeSlot::fromStrings($otherStart, $otherEnd);

    expect($slot->overlaps($other))->toBe($overlaps)
        ->and($other->overlaps($slot))->toBe($overlaps);
})->with([
    'identical' => ['2026-11-02T10:00:00Z', '2026-11-02T12:00:00Z', true],
    'partial at start' => ['2026-11-02T09:00:00Z', '2026-11-02T10:30:00Z', true],
    'partial at end' => ['2026-11-02T11:30:00Z', '2026-11-02T13:00:00Z', true],
    'contained' => ['2026-11-02T10:30:00Z', '2026-11-02T11:00:00Z', true],
    'containing' => ['2026-11-02T09:00:00Z', '2026-11-02T13:00:00Z', true],
    'back to back before' => ['2026-11-02T09:00:00Z', '2026-11-02T10:00:00Z', false],
    'back to back after' => ['2026-11-02T12:00:00Z', '2026-11-02T13:00:00Z', false],
    'disjoint' => ['2026-11-03T10:00:00Z', '2026-11-03T12:00:00Z', false],
]);

it('compares slots across time zones by instant', function (): void {
    $utc = TimeSlot::fromStrings('2026-11-02T13:00:00Z', '2026-11-02T14:00:00Z');
    $saoPaulo = TimeSlot::fromStrings('2026-11-02T10:00:00-03:00', '2026-11-02T11:00:00-03:00');

    expect($utc->equals($saoPaulo))->toBeTrue()
        ->and($utc->overlaps($saoPaulo))->toBeTrue();
});

it('contains instants using a half-open interval', function (): void {
    $slot = TimeSlot::fromStrings('2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');

    expect($slot->contains(new DateTimeImmutable('2026-11-02T10:00:00Z')))->toBeTrue()
        ->and($slot->contains(new DateTimeImmutable('2026-11-02T10:59:59Z')))->toBeTrue()
        ->and($slot->contains(new DateTimeImmutable('2026-11-02T11:00:00Z')))->toBeFalse();
});

it('counts real elapsed minutes across a DST change', function (): void {
    // New York falls back on 2026-11-01: 01:00-03:00 local spans three real hours.
    $tz = new DateTimeZone('America/New_York');
    $slot = new TimeSlot(
        new DateTimeImmutable('2026-11-01 01:00:00', $tz),
        new DateTimeImmutable('2026-11-01 03:00:00', $tz),
    );

    expect($slot->durationInMinutes())->toBe(180);
});

it('turns unparseable input into a domain exception', function (): void {
    TimeSlot::fromStrings('not a date', '2026-11-02T10:00:00Z');
})->throws(InvalidTimeSlot::class, '"not a date" is not a valid date and time.');

it('computes the intersection of two slots', function (): void {
    $slot = TimeSlot::fromStrings('2026-11-02T10:00:00Z', '2026-11-02T12:00:00Z');

    expect($slot->intersection(TimeSlot::fromStrings('2026-11-02T11:00:00Z', '2026-11-02T13:00:00Z'))?->equals(TimeSlot::fromStrings('2026-11-02T11:00:00Z', '2026-11-02T12:00:00Z')))->toBeTrue()
        ->and($slot->intersection(TimeSlot::fromStrings('2026-11-02T10:30:00Z', '2026-11-02T11:00:00Z'))?->durationInMinutes())->toBe(30)
        ->and($slot->intersection(TimeSlot::fromStrings('2026-11-02T12:00:00Z', '2026-11-02T13:00:00Z')))->toBeNull();
});
