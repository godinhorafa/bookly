<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Postgres;

use Domain\Shared\WeeklyWindow;
use Domain\Space\OpeningHours;

/**
 * Maps OpeningHours to the jsonb column, e.g. [{"weekdays":[1,2,3,4,5],"start":"08:00","end":"22:00"}].
 */
final class OpeningHoursJson
{
    public static function encode(OpeningHours $hours): string
    {
        return json_encode(array_map(static fn (WeeklyWindow $w): array => [
            'weekdays' => $w->weekdays,
            'start' => $w->startTime(),
            'end' => $w->endTime(),
        ], $hours->windows), JSON_THROW_ON_ERROR);
    }

    public static function decode(string $json): OpeningHours
    {
        /** @var list<array{weekdays: list<int>, start: string, end: string}> $windows */
        $windows = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new OpeningHours(...array_map(
            static fn (array $w): WeeklyWindow => new WeeklyWindow($w['weekdays'], $w['start'], $w['end']),
            $windows,
        ));
    }
}
