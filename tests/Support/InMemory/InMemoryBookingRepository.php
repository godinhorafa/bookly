<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Shared\TimeSlot;
use Domain\Space\SpaceId;

/**
 * Test double that honours the same contract as the PostgreSQL adapter,
 * verified by tests/Contracts/BookingRepositoryContract.php.
 */
final class InMemoryBookingRepository implements BookingRepository
{
    /** @var array<string, Booking> */
    private array $bookings = [];

    public function get(BookingId $id): Booking
    {
        if (! isset($this->bookings[$id->value])) {
            throw BookingNotFound::withId($id);
        }

        return clone $this->bookings[$id->value];
    }

    public function save(Booking $booking): void
    {
        foreach ($this->bookings as $existing) {
            if ($booking->conflictsWith($existing)) {
                throw SlotUnavailable::for($booking->spaceId, $booking->slot);
            }
        }

        // Like a database row, the stored copy carries state, not pending events.
        $stored = clone $booking;
        $stored->releaseEvents();
        $this->bookings[$booking->id->value] = $stored;
    }

    public function findOverlapping(SpaceId $spaceId, TimeSlot $slot): array
    {
        $matches = array_filter(
            $this->bookings,
            static fn (Booking $b): bool => $b->spaceId->equals($spaceId) && $b->holdsSlot() && $b->slot->overlaps($slot),
        );

        usort($matches, static fn (Booking $a, Booking $b): int => $a->slot->start <=> $b->slot->start);

        return array_map(static fn (Booking $b): Booking => clone $b, $matches);
    }

    public function countUpcomingFor(CustomerId $customerId, \DateTimeImmutable $now): int
    {
        return count(array_filter(
            $this->bookings,
            static fn (Booking $b): bool => $b->customerId->equals($customerId) && $b->holdsSlot() && $b->slot->end > $now,
        ));
    }
}
