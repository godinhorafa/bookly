<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

final readonly class DurationBetween extends CompositeSpecification
{
    public function __construct(private int $minimumMinutes, private int $maximumMinutes) {}

    public function isSatisfiedBy(BookingRequest $request): bool
    {
        $minutes = $request->slot->durationInMinutes();

        return $minutes >= $this->minimumMinutes && $minutes <= $this->maximumMinutes;
    }

    public function describe(): string
    {
        return "Bookings must last between {$this->minimumMinutes} and {$this->maximumMinutes} minutes.";
    }
}
