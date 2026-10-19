<?php

declare(strict_types=1);

namespace Domain\Booking;

use Domain\Booking\Exception\InvalidTimeSlot;

/**
 * Half-open interval [start, end) normalised to UTC.
 * Half-open bounds let back-to-back bookings (10:00-11:00, 11:00-12:00) coexist,
 * and match the '[)' range used by the database exclusion constraint.
 */
final readonly class TimeSlot
{
    public \DateTimeImmutable $start;

    public \DateTimeImmutable $end;

    public function __construct(\DateTimeImmutable $start, \DateTimeImmutable $end)
    {
        $utc = new \DateTimeZone('UTC');
        $start = $start->setTimezone($utc);
        $end = $end->setTimezone($utc);

        if ($end <= $start) {
            throw InvalidTimeSlot::endNotAfterStart($start, $end);
        }

        $this->start = $start;
        $this->end = $end;
    }

    public static function fromStrings(string $start, string $end): self
    {
        return new self(self::parse($start), self::parse($end));
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function contains(\DateTimeImmutable $instant): bool
    {
        return $instant >= $this->start && $instant < $this->end;
    }

    public function durationInMinutes(): int
    {
        return intdiv($this->end->getTimestamp() - $this->start->getTimestamp(), 60);
    }

    public function equals(self $other): bool
    {
        return $this->instant($this->start) === $this->instant($other->start)
            && $this->instant($this->end) === $this->instant($other->end);
    }

    /**
     * Compares by point in time, not object identity (=== on objects checks identity).
     */
    private static function parse(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw InvalidTimeSlot::unparseable($value);
        }
    }

    private function instant(\DateTimeImmutable $moment): string
    {
        return $moment->format('U.u');
    }
}
