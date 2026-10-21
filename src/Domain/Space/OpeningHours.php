<?php

declare(strict_types=1);

namespace Domain\Space;

use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;
use Domain\Space\Exception\InvalidSpace;

/**
 * When a space can be used, as weekly windows in the space's local time.
 * A slot is acceptable only if every one of its minutes falls inside a window,
 * so a 24/7 space accepts overnight bookings and a 08:00-22:00 one does not.
 */
final readonly class OpeningHours
{
    /** @var list<WeeklyWindow> */
    public array $windows;

    public function __construct(WeeklyWindow ...$windows)
    {
        $windows = array_values($windows);

        foreach ($windows as $i => $a) {
            foreach (array_slice($windows, $i + 1) as $b) {
                if ($a->overlaps($b)) {
                    throw InvalidSpace::overlappingOpeningHours();
                }
            }
        }

        $this->windows = $windows;
    }

    public static function alwaysOpen(): self
    {
        return new self(new WeeklyWindow([1, 2, 3, 4, 5, 6, 7], '00:00', '24:00'));
    }

    public function covers(TimeSlot $slot, \DateTimeZone $timezone): bool
    {
        $openMinutes = 0;
        foreach ($this->windows as $window) {
            $openMinutes += $window->minutesWithin($slot, $timezone);
        }

        // Windows never overlap (checked above), so the sum cannot double count.
        return $openMinutes === $slot->durationInMinutes();
    }
}
