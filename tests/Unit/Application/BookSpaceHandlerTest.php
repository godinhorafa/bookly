<?php

declare(strict_types=1);

use Application\Booking\BookSpace;
use Application\Booking\BookSpaceHandler;
use Domain\Booking\BookingStatus;
use Domain\Booking\Exception\InvalidTimeSlot;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Space\Exception\SpaceNotFound;
use Domain\Space\SpaceId;
use Tests\Support\Builders;
use Tests\Support\InMemory\InMemoryBookingRepository;
use Tests\Support\InMemory\InMemorySpaceRepository;

beforeEach(function (): void {
    $this->spaces = new InMemorySpaceRepository;
    $this->bookings = new InMemoryBookingRepository;
    $this->handler = new BookSpaceHandler($this->spaces, $this->bookings);

    $this->space = Builders::space(hourlyRate: 8_000);
    $this->spaces->save($this->space);
});

function bookCommand(string $spaceId, string $start = '2026-11-02T10:00:00-03:00', string $end = '2026-11-02T11:30:00-03:00'): BookSpace
{
    return new BookSpace($spaceId, 42, $start, $end);
}

it('creates a pending booking priced from the space rate', function (): void {
    $id = $this->handler->handle(bookCommand($this->space->id->value));

    $booking = $this->bookings->get($id);

    expect($booking->status())->toBe(BookingStatus::Pending)
        ->and($booking->customerId->value)->toBe(42)
        ->and($booking->slot->durationInMinutes())->toBe(90)
        ->and($booking->price->amount)->toBe(12_000);
});

it('refuses a slot that is already taken', function (): void {
    $this->handler->handle(bookCommand($this->space->id->value));

    $this->handler->handle(bookCommand($this->space->id->value, '2026-11-02T11:00:00-03:00', '2026-11-02T12:00:00-03:00'));
})->throws(SlotUnavailable::class);

it('fails for an unknown space', function (): void {
    $this->handler->handle(bookCommand(SpaceId::generate()->value));
})->throws(SpaceNotFound::class);

it('turns unparseable dates into a domain error', function (): void {
    $this->handler->handle(bookCommand($this->space->id->value, 'tomorrow-ish', '2026-11-02T12:00:00Z'));
})->throws(InvalidTimeSlot::class);
