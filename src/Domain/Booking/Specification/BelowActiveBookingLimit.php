<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

/**
 * Caps how many upcoming bookings one customer can hold, so nobody hoards a space.
 */
final readonly class BelowActiveBookingLimit extends CompositeSpecification
{
    public function __construct(private int $limit) {}

    public function isSatisfiedBy(BookingRequest $request): bool
    {
        return $request->activeBookingsOfCustomer < $this->limit;
    }

    public function describe(): string
    {
        return "A customer can hold at most {$this->limit} upcoming bookings.";
    }
}
