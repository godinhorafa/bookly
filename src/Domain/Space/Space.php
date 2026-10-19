<?php

declare(strict_types=1);

namespace Domain\Space;

use Domain\Booking\TimeSlot;
use Domain\Shared\Money;
use Domain\Space\Exception\InvalidSpace;

/**
 * A bookable place: a desk, a meeting room, a court.
 */
final readonly class Space
{
    public string $name;

    public \DateTimeZone $timezone;

    public function __construct(
        public SpaceId $id,
        string $name,
        string $timezone,
        public int $capacity,
        public Money $hourlyRate,
    ) {
        $name = trim($name);
        if ($name === '') {
            throw InvalidSpace::blankName();
        }

        if ($capacity < 1) {
            throw InvalidSpace::invalidCapacity($capacity);
        }

        if (! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw InvalidSpace::unknownTimezone($timezone);
        }

        $this->name = $name;
        $this->timezone = new \DateTimeZone($timezone);
    }

    /**
     * Base price, prorated by the minute. Dynamic pricing rules build on top of this.
     */
    public function basePriceFor(TimeSlot $slot): Money
    {
        return $this->hourlyRate->ratio($slot->durationInMinutes(), 60);
    }
}
