<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

final readonly class StartsWithMinimumNotice extends CompositeSpecification
{
    public function __construct(private int $minutes) {}

    public function isSatisfiedBy(BookingRequest $request): bool
    {
        return $request->slot->start->getTimestamp() - $request->requestedAt->getTimestamp() >= $this->minutes * 60;
    }

    public function describe(): string
    {
        return $this->minutes === 0
            ? 'Bookings must start in the future.'
            : "Bookings must start at least {$this->minutes} minutes from now.";
    }
}
