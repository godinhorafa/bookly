<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Space\Space;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SpaceResource extends JsonResource
{
    public function __construct(private readonly Space $space)
    {
        parent::__construct($space);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->space->id->value,
            'name' => $this->space->name,
            /** IANA time zone; opening and peak hours are in this zone. */
            'timezone' => $this->space->timezone->getName(),
            'capacity' => $this->space->capacity,
            'hourly_rate' => new MoneyResource($this->space->hourlyRate),
            'cancellation_policy' => $this->space->cancellationPolicy,
            'opening_hours' => WeeklyWindowResource::collection($this->space->openingHours->windows),
        ];
    }
}
