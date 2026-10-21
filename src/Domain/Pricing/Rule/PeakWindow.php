<?php

declare(strict_types=1);

namespace Domain\Pricing\Rule;

use Domain\Booking\TimeSlot;
use Domain\Pricing\Exception\InvalidPricingRule;

/**
 * A recurring local-time window, e.g. weekdays 18:00-22:00.
 * Expressed in wall-clock time so it follows the space's time zone across DST changes.
 */
final readonly class PeakWindow
{
    /** @var list<int> ISO-8601 weekdays, 1 = Monday ... 7 = Sunday */
    public array $weekdays;

    public int $startMinute;

    public int $endMinute;

    /**
     * @param  list<int>  $weekdays
     */
    public function __construct(array $weekdays, string $start, string $end)
    {
        if ($weekdays === [] || array_diff($weekdays, range(1, 7)) !== []) {
            throw InvalidPricingRule::invalidWeekdays();
        }

        $this->startMinute = $this->parseTime($start);
        $this->endMinute = $this->parseTime($end);

        if ($this->endMinute <= $this->startMinute) {
            throw InvalidPricingRule::emptyWindow($start, $end);
        }

        $this->weekdays = array_values(array_unique($weekdays));
    }

    public static function weekdays(string $start, string $end): self
    {
        return new self([1, 2, 3, 4, 5], $start, $end);
    }

    public static function weekends(string $start, string $end): self
    {
        return new self([6, 7], $start, $end);
    }

    /**
     * Minutes of the slot that fall inside this window, evaluated in the given time zone.
     */
    public function minutesWithin(TimeSlot $slot, \DateTimeZone $timezone): int
    {
        $day = $slot->start->setTimezone($timezone)->setTime(0, 0);
        $lastDay = $slot->end->setTimezone($timezone)->setTime(0, 0);
        $minutes = 0;

        while ($day <= $lastDay) {
            if (in_array((int) $day->format('N'), $this->weekdays, true)) {
                $window = new TimeSlot($this->at($day, $this->startMinute), $this->at($day, $this->endMinute));
                $minutes += $slot->intersection($window)?->durationInMinutes() ?? 0;
            }

            $day = $day->modify('+1 day');
        }

        return $minutes;
    }

    private function at(\DateTimeImmutable $day, int $minuteOfDay): \DateTimeImmutable
    {
        // setTime() resolves wall-clock time in the day's zone, so 18:00 stays 18:00 local across DST.
        return $day->setTime(intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }

    private function parseTime(string $time): int
    {
        if (preg_match('/^([01]\d|2[0-4]):([0-5]\d)$/', $time, $m) !== 1 || ($m[1] === '24' && $m[2] !== '00')) {
            throw InvalidPricingRule::invalidTime($time);
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
