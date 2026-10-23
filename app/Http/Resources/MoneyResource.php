<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Shared\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An amount in minor units (cents) and its ISO 4217 currency.
 */
final class MoneyResource extends JsonResource
{
    public function __construct(private readonly Money $money)
    {
        parent::__construct($money);
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** In minor units, e.g. 12050 for BRL 120.50. */
            'amount' => $this->money->amount,
            'currency' => $this->money->currency->value,
        ];
    }
}
