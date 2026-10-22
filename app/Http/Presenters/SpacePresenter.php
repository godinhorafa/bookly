<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use Application\Space\Availability;
use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;
use Domain\Space\Space;

final class SpacePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function space(Space $space): array
    {
        return [
            'id' => $space->id->value,
            'name' => $space->name,
            'timezone' => $space->timezone->getName(),
            'capacity' => $space->capacity,
            'hourly_rate' => BookingPresenter::money($space->hourlyRate),
            'cancellation_policy' => $space->cancellationPolicy->value,
            'opening_hours' => array_map(static fn (WeeklyWindow $w): array => [
                'weekdays' => $w->weekdays,
                'start' => $w->startTime(),
                'end' => $w->endTime(),
            ], $space->openingHours->windows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function availability(Availability $availability): array
    {
        return [
            'space_id' => $availability->space->id->value,
            'from' => $availability->range->start->format(DATE_ATOM),
            'to' => $availability->range->end->format(DATE_ATOM),
            'busy' => array_map(static fn (TimeSlot $slot): array => [
                'start' => $slot->start->format(DATE_ATOM),
                'end' => $slot->end->format(DATE_ATOM),
            ], $availability->busy),
        ];
    }
}
