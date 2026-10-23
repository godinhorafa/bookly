<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Application\Space\Availability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AvailabilityResource extends JsonResource
{
    public function __construct(private readonly Availability $availability)
    {
        parent::__construct($availability);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'space_id' => $this->availability->space->id->value,
            'from' => $this->availability->range->start->format(DATE_ATOM),
            'to' => $this->availability->range->end->format(DATE_ATOM),
            /** Slots held by active bookings, in order. Who booked them is not disclosed. */
            'busy' => SlotResource::collection($this->availability->busy),
        ];
    }
}
