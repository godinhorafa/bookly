<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Booking\BookingCancellation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CancellationResource extends JsonResource
{
    public function __construct(private readonly BookingCancellation $cancellation)
    {
        parent::__construct($cancellation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cancelled_at' => $this->cancellation->cancelledAt->format(DATE_ATOM),
            'refund' => new MoneyResource($this->cancellation->refund),
        ];
    }
}
