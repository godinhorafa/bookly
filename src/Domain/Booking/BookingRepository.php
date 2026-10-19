<?php

declare(strict_types=1);

namespace Domain\Booking;

use Domain\Booking\Exception\BookingNotFound;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Space\SpaceId;

interface BookingRepository
{
    /**
     * @throws BookingNotFound
     */
    public function get(BookingId $id): Booking;

    /**
     * Inserts or updates the booking.
     *
     * Implementations must guarantee, atomically, that no two bookings holding
     * a slot overlap for the same space, even under concurrent writes.
     *
     * @throws SlotUnavailable
     */
    public function save(Booking $booking): void;

    /**
     * Bookings that currently hold a slot overlapping the given one.
     *
     * @return list<Booking>
     */
    public function findOverlapping(SpaceId $spaceId, TimeSlot $slot): array;
}
