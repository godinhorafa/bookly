<?php

declare(strict_types=1);

namespace Application\Space;

use Domain\Booking\Booking;
use Domain\Booking\BookingRepository;
use Domain\Shared\Exception\InvalidTimeSlot;
use Domain\Shared\TimeSlot;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;

/**
 * Read side for browsing spaces and their calendars.
 */
final readonly class SpaceQueries
{
    public const int MAX_RANGE_DAYS = 62;

    public function __construct(
        private SpaceRepository $spaces,
        private BookingRepository $bookings,
    ) {}

    /**
     * @return list<Space>
     */
    public function all(): array
    {
        return $this->spaces->all();
    }

    /**
     * Busy slots only: who booked them is nobody else's business.
     */
    public function availability(string $spaceId, string $from, string $to): Availability
    {
        $space = $this->spaces->get(SpaceId::fromString($spaceId));
        $range = TimeSlot::fromStrings($from, $to);

        if ($range->durationInMinutes() > self::MAX_RANGE_DAYS * 24 * 60) {
            throw InvalidTimeSlot::rangeTooLong(self::MAX_RANGE_DAYS);
        }

        return new Availability(
            $space,
            $range,
            array_map(static fn (Booking $b): TimeSlot => $b->slot, $this->bookings->findOverlapping($space->id, $range)),
        );
    }
}
