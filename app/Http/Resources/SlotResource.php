<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Shared\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SlotResource extends JsonResource
{
    public function __construct(private readonly TimeSlot $slot)
    {
        parent::__construct($slot);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'start' => $this->slot->start->format(DATE_ATOM),
            'end' => $this->slot->end->format(DATE_ATOM),
        ];
    }
}
