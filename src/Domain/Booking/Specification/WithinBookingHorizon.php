<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

final readonly class WithinBookingHorizon extends CompositeSpecification
{
    public function __construct(private int $days) {}

    public function isSatisfiedBy(BookingRequest $request): bool
    {
        return $request->slot->start <= $request->requestedAt->modify("+{$this->days} days");
    }

    public function describe(): string
    {
        return "Bookings can be made at most {$this->days} days in advance.";
    }
}
