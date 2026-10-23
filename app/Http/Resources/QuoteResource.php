<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Pricing\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How the price was built: the base price, then each adjustment in the order it was applied.
 */
final class QuoteResource extends JsonResource
{
    public function __construct(private readonly Quote $quote)
    {
        parent::__construct($quote);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'base' => new MoneyResource($this->quote->base),
            'adjustments' => AdjustmentResource::collection($this->quote->adjustments),
            'total' => new MoneyResource($this->quote->total),
        ];
    }
}
