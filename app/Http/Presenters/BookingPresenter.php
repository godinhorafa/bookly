<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use Domain\Booking\Booking;
use Domain\Booking\BookingCancellation;
use Domain\Pricing\Adjustment;
use Domain\Pricing\Quote;
use Domain\Shared\Money;

/**
 * Turns domain objects into the public JSON shape of a booking.
 */
final class BookingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function booking(Booking $booking, ?Quote $quote = null): array
    {
        return array_filter([
            'id' => $booking->id->value,
            'status' => $booking->status()->value,
            'space_id' => $booking->spaceId->value,
            'start' => $booking->slot->start->format(DATE_ATOM),
            'end' => $booking->slot->end->format(DATE_ATOM),
            'price' => self::money($booking->price),
            'quote' => $quote instanceof Quote ? self::quote($quote) : null,
            'cancellation' => $booking->cancellation() instanceof BookingCancellation ? [
                'cancelled_at' => $booking->cancellation()->cancelledAt->format(DATE_ATOM),
                'refund' => self::money($booking->cancellation()->refund),
            ] : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{base: array{amount: int, currency: string}, adjustments: list<array<string, mixed>>, total: array{amount: int, currency: string}}
     */
    public static function quote(Quote $quote): array
    {
        return [
            'base' => self::money($quote->base),
            'adjustments' => array_map(static fn (Adjustment $a): array => [
                'rule' => $a->rule,
                'description' => $a->description,
                'kind' => $a->kind->value,
                'amount' => self::money($a->amount),
            ], $quote->adjustments),
            'total' => self::money($quote->total),
        ];
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public static function money(Money $money): array
    {
        return ['amount' => $money->amount, 'currency' => $money->currency->value];
    }
}
