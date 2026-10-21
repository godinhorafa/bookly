<?php

declare(strict_types=1);

namespace Domain\Booking\Event;

use Domain\Booking\Booking;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Money;

final readonly class BookingCancelled implements DomainEvent
{
    private function __construct(
        public string $bookingId,
        public string $spaceId,
        public int $customerId,
        public int $refundAmount,
        public string $currency,
    ) {}

    public static function of(Booking $booking, Money $refund): self
    {
        return new self(
            $booking->id->value,
            $booking->spaceId->value,
            $booking->customerId->value,
            $refund->amount,
            $refund->currency->value,
        );
    }

    public static function name(): string
    {
        return 'booking.cancelled';
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
            'refund_amount' => $this->refundAmount,
            'currency' => $this->currency,
        ];
    }
}
