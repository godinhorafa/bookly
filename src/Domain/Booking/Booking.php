<?php

declare(strict_types=1);

namespace Domain\Booking;

use Domain\Booking\Exception\BookingAlreadyStarted;
use Domain\Booking\Exception\InvalidBookingTransition;
use Domain\Cancellation\CancellationPolicy;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Space\SpaceId;

/**
 * Aggregate root. Identity, space, customer, slot and price never change after
 * the booking is requested; only the status (and, once cancelled, the refund) evolve.
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
        private ?BookingCancellation $cancellation = null,
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
        ?BookingCancellation $cancellation = null,
    ): self {
        return new self($id, $spaceId, $customerId, $slot, $price, $status, $cancellation);
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->transitionTo(BookingStatus::Confirmed);
    }

    /**
     * Cancels the booking and returns the refund owed to the customer.
     * Only confirmed (paid) bookings are refunded; a pending one has nothing to give back.
     */
    public function cancel(\DateTimeImmutable $at, CancellationPolicy $policy): Money
    {
        $this->assertCanTransitionTo(BookingStatus::Cancelled);

        if ($at >= $this->slot->start) {
            throw BookingAlreadyStarted::cannotCancel($this->id);
        }

        $refund = $this->status === BookingStatus::Confirmed
            ? $policy->refundFor($this->price, $this->slot, $at)
            : Money::zero($this->price->currency);

        $this->status = BookingStatus::Cancelled;
        $this->cancellation = new BookingCancellation($at, $refund);

        return $refund;
    }

    public function cancellation(): ?BookingCancellation
    {
        return $this->cancellation;
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
        $this->assertCanTransitionTo($next);
        $this->status = $next;
    }

    private function assertCanTransitionTo(BookingStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidBookingTransition::between($this->id, $this->status, $next);
        }
    }
}
