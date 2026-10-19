<?php

declare(strict_types=1);

namespace Application\Booking;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Booking\TimeSlot;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;

final readonly class BookSpaceHandler
{
    public function __construct(
        private SpaceRepository $spaces,
        private BookingRepository $bookings,
    ) {}

    public function handle(BookSpace $command): BookingId
    {
        $space = $this->spaces->get(SpaceId::fromString($command->spaceId));
        $slot = TimeSlot::fromStrings($command->start, $command->end);

        // Fast path for a friendly error. It is not race-free on its own:
        // BookingRepository::save() enforces the invariant atomically.
        if ($this->bookings->findOverlapping($space->id, $slot) !== []) {
            throw SlotUnavailable::for($space->id, $slot);
        }

        $booking = Booking::request(
            BookingId::generate(),
            $space->id,
            new CustomerId($command->customerId),
            $slot,
            $space->basePriceFor($slot),
        );

        $this->bookings->save($booking);

        return $booking->id;
    }
}
