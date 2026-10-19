<?php

declare(strict_types=1);

namespace Domain\Booking;

use Domain\Booking\Exception\InvalidBookingTransition;
use Domain\Shared\Money;
use Domain\Space\SpaceId;

/**
 * Aggregate root. Identity, space, customer, slot and price never change after
 * the booking is requested; only the status moves through its lifecycle.
 */
final class Booking
{
    private function __construct(
        public readonly BookingId $id,
        public readonly SpaceId $spaceId,
        public readonly CustomerId $customerId,
        public readonly TimeSlot $slot,
        public readonly Money $price,
        private BookingStatus $status,
    ) {}

    public static function request(
        BookingId $id,
        SpaceId $spaceId,
        CustomerId $customerId,
        TimeSlot $slot,
        Money $price,
    ): self {
        return new self($id, $spaceId, $customerId, $slot, $price, BookingStatus::Pending);
    }

    /**
     * Rebuilds a booking from storage without replaying its lifecycle.
     */
    public static function reconstitute(
        BookingId $id,
        SpaceId $spaceId,
        CustomerId $customerId,
        TimeSlot $slot,
        Money $price,
        BookingStatus $status,
    ): self {
        return new self($id, $spaceId, $customerId, $slot, $price, $status);
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->transitionTo(BookingStatus::Confirmed);
    }

    public function cancel(): void
    {
        $this->transitionTo(BookingStatus::Cancelled);
    }

    public function holdsSlot(): bool
    {
        return $this->status->holdsSlot();
    }

    public function conflictsWith(self $other): bool
    {
        return ! $this->id->equals($other->id)
            && $this->spaceId->equals($other->spaceId)
            && $this->holdsSlot()
            && $other->holdsSlot()
            && $this->slot->overlaps($other->slot);
    }

    private function transitionTo(BookingStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidBookingTransition::between($this->id, $this->status, $next);
        }

        $this->status = $next;
    }
}
