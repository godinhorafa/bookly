<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

final readonly class WithinOpeningHours extends CompositeSpecification
{
    public function isSatisfiedBy(BookingRequest $request): bool
    {
        return $request->space->isOpenDuring($request->slot);
    }

    public function describe(): string
    {
        return 'Bookings must fall entirely within the opening hours of the space.';
    }
}
