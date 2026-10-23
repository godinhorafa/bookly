<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Shared\WeeklyWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WeeklyWindowResource extends JsonResource
{
    public function __construct(private readonly WeeklyWindow $window)
    {
        parent::__construct($window);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** ISO weekdays, 1 = Monday ... 7 = Sunday. */
            'weekdays' => $this->window->weekdays,
            /** Local time, HH:MM. */
            'start' => $this->window->startTime(),
            /** Local time, HH:MM; may be 24:00. */
            'end' => $this->window->endTime(),
        ];
    }
}
