<?php

declare(strict_types=1);

use Application\Booking\BookingQueries;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Space\SpaceId;
use Tests\Support\Builders;
use Tests\Support\InMemory\InMemoryBookingRepository;

beforeEach(function (): void {
    $this->bookings = new InMemoryBookingRepository;
    $this->queries = new BookingQueries($this->bookings);

    $this->mine = Builders::booking(SpaceId::generate(), new CustomerId(7));
    $this->theirs = Builders::booking(SpaceId::generate(), new CustomerId(8));
    $this->bookings->save($this->mine);
    $this->bookings->save($this->theirs);
});

it('lists only the customer\'s own bookings', function (): void {
    expect(array_map(static fn ($booking): string => $booking->id->value, $this->queries->ofCustomer(7)))->toBe([$this->mine->id->value]);
});

it('returns one of the customer\'s bookings', function (): void {
    expect($this->queries->get($this->mine->id->value, 7)->id->equals($this->mine->id))->toBeTrue();
});

it('makes another customer\'s booking look like a missing one', function (): void {
    $this->queries->get($this->theirs->id->value, 7);
})->throws(BookingNotFound::class);
