<?php

declare(strict_types=1);

namespace Application\Booking;

final readonly class CancelBooking
{
    public function __construct(
        public string $bookingId,
        public int $customerId,
    ) {}
}
