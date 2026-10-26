<?php

declare(strict_types=1);

use Application\Space\SpaceQueries;
use Domain\Booking\CustomerId;
use Domain\Shared\Exception\InvalidTimeSlot;
use Domain\Shared\TimeSlot;
use Tests\Support\Builders;
use Tests\Support\InMemory\InMemoryBookingRepository;
use Tests\Support\InMemory\InMemorySpaceRepository;

beforeEach(function (): void {
    $this->spaces = new InMemorySpaceRepository;
    $this->bookings = new InMemoryBookingRepository;
    $this->queries = new SpaceQueries($this->spaces, $this->bookings);

    $this->space = Builders::space(name: 'Tennis court');
    $this->spaces->save($this->space);
});

it('lists every space by name', function (): void {
    $this->spaces->save(Builders::space(name: 'Event hall'));

    expect(array_map(static fn ($space): string => $space->name, $this->queries->all()))->toBe(['Event hall', 'Tennis court']);
});

it('shows busy slots in the range, without saying whose they are', function (): void {
    $customer = new CustomerId(7);
    $this->bookings->save(Builders::booking($this->space->id, $customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z'));
    $this->bookings->save(Builders::cancel(Builders::booking($this->space->id, $customer, '2026-11-02T12:00:00Z', '2026-11-02T13:00:00Z')));
    $this->bookings->save(Builders::booking($this->space->id, $customer, '2026-11-05T10:00:00Z', '2026-11-05T11:00:00Z'));

    $availability = $this->queries->availability($this->space->id->value, '2026-11-02T00:00:00Z', '2026-11-03T00:00:00Z');

    expect($availability->space)->toBe($this->space)
        ->and($availability->range->equals(TimeSlot::fromStrings('2026-11-02T00:00:00Z', '2026-11-03T00:00:00Z')))->toBeTrue()
        ->and($availability->busy)->toHaveCount(1)
        ->and($availability->busy[0])->toBeInstanceOf(TimeSlot::class)
        ->and($availability->busy[0]->equals(TimeSlot::fromStrings('2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z')))->toBeTrue();
});

it('allows ranges of up to 62 days', function (): void {
    $availability = $this->queries->availability($this->space->id->value, '2026-11-01T00:00:00Z', '2027-01-02T00:00:00Z');

    expect($availability->range->durationInMinutes())->toBe(62 * 24 * 60);
});

it('refuses longer ranges, which would make the query unbounded', function (): void {
    $this->queries->availability($this->space->id->value, '2026-11-01T00:00:00Z', '2027-01-02T00:01:00Z');
})->throws(InvalidTimeSlot::class, 'A range can span at most 62 days.');
