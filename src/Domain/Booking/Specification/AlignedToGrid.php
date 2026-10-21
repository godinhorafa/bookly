<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

/**
 * Start and end must sit on the booking grid (e.g. every 15 minutes) in the
 * space's local time, which keeps calendars readable and avoids unbookable
 * 7-minute gaps. Checked locally so a 60-minute grid works in UTC+05:30 too.
 */
final readonly class AlignedToGrid extends CompositeSpecification
{
    public function __construct(private int $minutes) {}

    public function isSatisfiedBy(BookingRequest $request): bool
    {
        $timezone = $request->space->timezone;

        return $this->isOnGrid($request->slot->start->setTimezone($timezone))
            && $this->isOnGrid($request->slot->end->setTimezone($timezone));
    }

    public function describe(): string
    {
        return "Bookings must start and end on a {$this->minutes}-minute boundary.";
    }

    private function isOnGrid(\DateTimeImmutable $local): bool
    {
        $minuteOfDay = (int) $local->format('G') * 60 + (int) $local->format('i');

        return $local->format('s.u') === '00.000000' && $minuteOfDay % $this->minutes === 0;
    }
}
