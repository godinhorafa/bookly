<?php

declare(strict_types=1);

namespace Application\Booking;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Pricing\Quote;

final readonly class BookSpaceResult
{
    public BookingId $bookingId;

    public function __construct(
        public Booking $booking,
        public Quote $quote,
    ) {
        $this->bookingId = $booking->id;
    }
}
