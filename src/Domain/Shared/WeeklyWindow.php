<?php

declare(strict_types=1);

namespace Domain\Shared;

use Domain\Shared\Exception\InvalidWeeklyWindow;

/**
 * A recurring local-time window, e.g. weekdays 18:00-22:00. Used for peak hours
 * and opening hours. Expressed in wall-clock time so it follows the space's time
 * zone across DST changes.
 */
final readonly class WeeklyWindow
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
            throw InvalidWeeklyWindow::invalidWeekdays();
        }

        $this->startMinute = $this->parseTime($start);
        $this->endMinute = $this->parseTime($end);

        if ($this->endMinute <= $this->startMinute) {
            throw InvalidWeeklyWindow::emptyWindow($start, $end);
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
     * Whether both windows share at least one minute on a common weekday.
     */
    public function overlaps(self $other): bool
    {
        return array_intersect($this->weekdays, $other->weekdays) !== []
            && $this->startMinute < $other->endMinute
            && $other->startMinute < $this->endMinute;
    }

    public function startTime(): string
    {
        return $this->format($this->startMinute);
    }

    public function endTime(): string
    {
        return $this->format($this->endMinute);
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

    private function format(int $minuteOfDay): string
    {
        return sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }

    private function at(\DateTimeImmutable $day, int $minuteOfDay): \DateTimeImmutable
    {
        // setTime() resolves wall-clock time in the day's zone, so 18:00 stays 18:00 local across DST.
        return $day->setTime(intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }

    private function parseTime(string $time): int
    {
        if (preg_match('/^([01]\d|2[0-4]):([0-5]\d)$/', $time, $m) !== 1 || ($m[1] === '24' && $m[2] !== '00')) {
            throw InvalidWeeklyWindow::invalidTime($time);
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
