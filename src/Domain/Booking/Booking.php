<?php

declare(strict_types=1);

namespace Domain\Booking;

use Domain\Booking\Event\BookingCancelled;
use Domain\Booking\Event\BookingConfirmed;
use Domain\Booking\Event\BookingExpired;
use Domain\Booking\Event\BookingRequested;
use Domain\Booking\Exception\BookingAlreadyStarted;
use Domain\Booking\Exception\InvalidBookingTransition;
use Domain\Cancellation\CancellationPolicy;
use Domain\Shared\Event\RecordsEvents;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Space\SpaceId;

/**
 * Aggregate root. Identity, space, customer, slot, price and payment deadline never
 * change after the booking is requested; only the status (and, once cancelled, the refund) evolve.
 */
final class Booking
{
    use RecordsEvents;

    private function __construct(
        public readonly BookingId $id,
        public readonly SpaceId $spaceId,
        public readonly CustomerId $customerId,
        public readonly TimeSlot $slot,
        public readonly Money $price,
        /** Until when a pending booking holds its slot without being paid. UTC. */
        public readonly \DateTimeImmutable $holdUntil,
        private BookingStatus $status,
        private ?BookingCancellation $cancellation = null,
    ) {}

    public static function request(
        BookingId $id,
        SpaceId $spaceId,
        CustomerId $customerId,
        TimeSlot $slot,
        Money $price,
        \DateTimeImmutable $holdUntil,
    ): self {
        $booking = new self($id, $spaceId, $customerId, $slot, $price, self::utc($holdUntil), BookingStatus::Pending);
        $booking->record(BookingRequested::of($booking));

        return $booking;
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
        \DateTimeImmutable $holdUntil,
        BookingStatus $status,
        ?BookingCancellation $cancellation = null,
    ): self {
        return new self($id, $spaceId, $customerId, $slot, $price, self::utc($holdUntil), $status, $cancellation);
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    /**
     * Whether an unpaid booking has run out of time to be paid.
     */
    public function holdHasEnded(\DateTimeImmutable $now): bool
    {
        return $this->status === BookingStatus::Pending && $now >= $this->holdUntil;
    }

    /**
     * Releases the slot of a booking that was not paid in time.
     */
    public function expire(\DateTimeImmutable $now): void
    {
        $this->assertCanTransitionTo(BookingStatus::Expired);

        if ($now < $this->holdUntil) {
            throw InvalidBookingTransition::holdNotOver($this->id, $this->holdUntil);
        }

        $this->status = BookingStatus::Expired;
        $this->record(BookingExpired::of($this));
    }

    public function confirm(): void
    {
        $this->transitionTo(BookingStatus::Confirmed);
        $this->record(BookingConfirmed::of($this));
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
        $this->record(BookingCancelled::of($this, $refund));

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

    private static function utc(\DateTimeImmutable $instant): \DateTimeImmutable
    {
        return $instant->setTimezone(new \DateTimeZone('UTC'));
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
