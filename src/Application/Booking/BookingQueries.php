<?php

declare(strict_types=1);

namespace Application\Booking;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;

/**
 * Read side for a customer's own bookings. Ownership is enforced here, not in
 * controllers: another customer's booking looks exactly like a missing one.
 */
final readonly class BookingQueries
{
    public function __construct(private BookingRepository $bookings) {}

    /**
     * @return list<Booking>
     */
    public function ofCustomer(int $customerId): array
    {
        return $this->bookings->forCustomer(new CustomerId($customerId));
    }

    public function get(string $bookingId, int $customerId): Booking
    {
        $id = BookingId::fromString($bookingId);
        $booking = $this->bookings->get($id);

        if (! $booking->customerId->equals(new CustomerId($customerId))) {
            throw BookingNotFound::withId($id);
        }

        return $booking;
    }
}
