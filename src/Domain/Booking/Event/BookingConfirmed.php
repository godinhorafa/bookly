<?php

declare(strict_types=1);

namespace Domain\Booking\Event;

use Domain\Booking\Booking;
use Domain\Shared\Event\DomainEvent;

final readonly class BookingConfirmed implements DomainEvent
{
    private function __construct(
        public string $bookingId,
        public string $spaceId,
        public int $customerId,
        public string $start,
        public string $end,
        public int $paidAmount,
        public string $currency,
    ) {}

    public static function of(Booking $booking): self
    {
        return new self(
            $booking->id->value,
            $booking->spaceId->value,
            $booking->customerId->value,
            $booking->slot->start->format(DATE_ATOM),
            $booking->slot->end->format(DATE_ATOM),
            $booking->price->amount,
            $booking->price->currency->value,
        );
    }

    public static function name(): string
    {
        return 'booking.confirmed';
    }

    public function aggregateId(): string
    {
        return $this->bookingId;
    }

    public function payload(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'space_id' => $this->spaceId,
            'customer_id' => $this->customerId,
            'start' => $this->start,
            'end' => $this->end,
            'paid_amount' => $this->paidAmount,
            'currency' => $this->currency,
        ];
    }
}
