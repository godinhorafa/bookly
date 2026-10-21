<?php

declare(strict_types=1);

namespace Application\Booking;

use Domain\Booking\BookingId;
use Domain\Pricing\Quote;

final readonly class BookSpaceResult
{
    public function __construct(
        public BookingId $bookingId,
        public Quote $quote,
    ) {}
}
