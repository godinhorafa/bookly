<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

use Domain\Booking\CustomerId;
use Domain\Shared\TimeSlot;
use Domain\Space\Space;

/**
 * The candidate every booking specification is evaluated against.
 * It carries any facts a rule needs (such as the customer's active bookings),
 * so specifications stay pure functions with no repository access.
 */
final readonly class BookingRequest
{
    public function __construct(
        public Space $space,
        public TimeSlot $slot,
        public CustomerId $customerId,
        public \DateTimeImmutable $requestedAt,
        public int $activeBookingsOfCustomer,
    ) {}
}
