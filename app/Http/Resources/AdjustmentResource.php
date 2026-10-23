<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Pricing\Adjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdjustmentResource extends JsonResource
{
    public function __construct(private readonly Adjustment $adjustment)
    {
        parent::__construct($adjustment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** Machine-readable rule id, e.g. `peak_hours`, `long_booking`, `coupon`. */
            'rule' => $this->adjustment->rule,
            'description' => $this->adjustment->description,
            'kind' => $this->adjustment->kind,
            'amount' => new MoneyResource($this->adjustment->amount),
        ];
    }
}
