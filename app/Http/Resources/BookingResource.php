<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Booking\Booking;
use Domain\Booking\BookingCancellation;
use Domain\Pricing\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookingResource extends JsonResource
{
    private ?Quote $quote = null;

    // Single-argument constructor: collection() passes each item's key as a second argument.
    public function __construct(private readonly Booking $booking)
    {
        parent::__construct($booking);
    }

    public function withQuote(Quote $quote): self
    {
        $resource = clone $this;
        $resource->quote = $quote;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cancellation = $this->booking->cancellation();

        return [
            'id' => $this->booking->id->value,
            'status' => $this->booking->status(),
            'space_id' => $this->booking->spaceId->value,
            /** ISO 8601, UTC. */
            'start' => $this->booking->slot->start->format(DATE_ATOM),
            /** ISO 8601, UTC; the slot is half-open: [start, end). */
            'end' => $this->booking->slot->end->format(DATE_ATOM),
            'price' => new MoneyResource($this->booking->price),
            /** Only in the response that created the booking. */
            'quote' => $this->when($this->quote instanceof Quote, fn (): ?QuoteResource => $this->quote instanceof Quote ? new QuoteResource($this->quote) : null),
            'cancellation' => $this->when($cancellation instanceof BookingCancellation, fn (): ?CancellationResource => $cancellation instanceof BookingCancellation ? new CancellationResource($cancellation) : null),
        ];
    }
}
