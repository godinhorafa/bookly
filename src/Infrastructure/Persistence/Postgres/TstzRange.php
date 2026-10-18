<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Postgres;

use Domain\Booking\TimeSlot;

/**
 * Maps the TimeSlot value object to and from PostgreSQL's tstzrange type.
 * Bounds are always '[)' so the database agrees with TimeSlot::overlaps().
 */
final class TstzRange
{
    private const string FORMAT = 'Y-m-d H:i:sP';

    public static function fromTimeSlot(TimeSlot $slot): string
    {
        return sprintf('[%s,%s)', $slot->start->format(self::FORMAT), $slot->end->format(self::FORMAT));
    }

    public static function toTimeSlot(string $range): TimeSlot
    {
        if (preg_match('/^\[\"?([^",]+)\"?,\"?([^"\)]+)\"?\)$/', $range, $matches) !== 1) {
            throw new \UnexpectedValueException("Unsupported tstzrange literal: {$range}");
        }

        return TimeSlot::fromStrings($matches[1], $matches[2]);
    }
}
