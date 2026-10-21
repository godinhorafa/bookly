<?php

declare(strict_types=1);

namespace Application\Booking;

use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Shared\Clock;
use Domain\Shared\Money;
use Domain\Space\SpaceRepository;

final readonly class CancelBookingHandler
{
    public function __construct(
        private BookingRepository $bookings,
        private SpaceRepository $spaces,
        private Clock $clock,
    ) {}

    /**
     * @return Money the refund owed to the customer
     */
    public function handle(CancelBooking $command): Money
    {
        $id = BookingId::fromString($command->bookingId);
        $booking = $this->bookings->get($id);

        // Someone else's booking looks exactly like a missing one, so ids can't be probed.
        if (! $booking->customerId->equals(new CustomerId($command->customerId))) {
            throw BookingNotFound::withId($id);
        }

        $space = $this->spaces->get($booking->spaceId);
        $refund = $booking->cancel($this->clock->now(), $space->cancellationPolicy->policy());

        $this->bookings->save($booking);

        return $refund;
    }
}
