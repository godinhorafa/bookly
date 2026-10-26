<?php

declare(strict_types=1);

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Shared\TimeSlot;
use Tests\Contracts\BookingRepositoryFixture;
use Tests\Support\Builders;

/**
 * Behaviour every BookingRepository must have. Running the same suite against
 * the in-memory fake and the PostgreSQL adapter keeps the fake honest.
 *
 * @param  Closure(): BookingRepositoryFixture  $setUp
 */
function bookingRepositoryContract(Closure $setUp): void
{
    beforeEach(function () use ($setUp): void {
        $this->fx = $setUp();
    });

    it('persists a booking and reads it back', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $booking = Builders::booking($fx->space, $fx->customer, '2026-11-02T10:00:00-03:00', '2026-11-02T11:30:00-03:00');

        $fx->repository->save($booking);
        $found = $fx->repository->get($booking->id);

        expect($found->id->equals($booking->id))->toBeTrue()
            ->and($found->spaceId->equals($fx->space))->toBeTrue()
            ->and($found->customerId->equals($fx->customer))->toBeTrue()
            ->and($found->slot->equals($booking->slot))->toBeTrue()
            ->and($found->price->equals($booking->price))->toBeTrue()
            ->and($found->status())->toBe(BookingStatus::Pending);
    });

    it('throws when the booking does not exist', function (): void {
        $this->fx->repository->get(BookingId::generate());
    })->throws(BookingNotFound::class);

    it('persists status changes', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $booking = Builders::booking($fx->space, $fx->customer);
        $fx->repository->save($booking);

        $booking->confirm();
        $fx->repository->save($booking);

        expect($fx->repository->get($booking->id)->status())->toBe(BookingStatus::Confirmed);
    });

    it('does not let a booking conflict with itself when updated', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $booking = Builders::booking($fx->space, $fx->customer);

        $fx->repository->save($booking);
        $fx->repository->save($booking);

        expect($fx->repository->get($booking->id)->status())->toBe(BookingStatus::Pending);
    });

    it('rejects an overlapping booking for the same space', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T12:00:00Z'));

        $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-02T11:00:00Z', '2026-11-02T13:00:00Z'));
    })->throws(SlotUnavailable::class);

    it('accepts back-to-back bookings and the same slot in another space', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z'));
        $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-02T11:00:00Z', '2026-11-02T12:00:00Z'));
        $fx->repository->save(Builders::booking($fx->otherSpace, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z'));

        $all = $fx->repository->findOverlapping($fx->space, TimeSlot::fromStrings('2026-11-02T00:00:00Z', '2026-11-03T00:00:00Z'));

        expect($all)->toHaveCount(2);
    });

    it('frees the slot when a booking is cancelled', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $first = Builders::booking($fx->space, $fx->customer);
        $fx->repository->save($first);

        Builders::cancel($first);
        $fx->repository->save($first);
        $fx->repository->save(Builders::booking($fx->space, $fx->customer));

        expect($fx->repository->findOverlapping($fx->space, $first->slot))->toHaveCount(1);
    });

    it('finds only active overlapping bookings of the space, ordered by start', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $late = Builders::booking($fx->space, $fx->customer, '2026-11-02T14:00:00Z', '2026-11-02T15:00:00Z');
        $early = Builders::booking($fx->space, $fx->customer, '2026-11-02T09:00:00Z', '2026-11-02T10:30:00Z');
        $cancelled = Builders::booking($fx->space, $fx->customer, '2026-11-02T12:00:00Z', '2026-11-02T13:00:00Z');
        Builders::cancel($cancelled);
        $elsewhere = Builders::booking($fx->otherSpace, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
        $outside = Builders::booking($fx->space, $fx->customer, '2026-11-02T16:00:00Z', '2026-11-02T17:00:00Z');

        foreach ([$late, $early, $cancelled, $elsewhere, $outside] as $booking) {
            $fx->repository->save($booking);
        }

        $found = $fx->repository->findOverlapping($fx->space, TimeSlot::fromStrings('2026-11-02T10:00:00Z', '2026-11-02T15:30:00Z'));

        expect(array_map(static fn (Booking $b): string => $b->id->value, $found))
            ->toBe([$early->id->value, $late->id->value]);
    });

    it('persists the cancellation time and refund', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $booking = Builders::booking($fx->space, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
        $booking->confirm();
        $fx->repository->save($booking);

        // 36h before the start under the moderate policy: 50% back
        Builders::cancel($booking, '2026-10-31T22:00:00-03:00');
        $fx->repository->save($booking);

        $cancellation = $fx->repository->get($booking->id)->cancellation();

        expect($cancellation?->cancelledAt->format(DATE_ATOM))->toBe('2026-11-01T01:00:00+00:00')
            ->and($cancellation?->refund->amount)->toBe(2_500);
    });

    it('has no cancellation details for active bookings', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $booking = Builders::booking($fx->space, $fx->customer);
        $fx->repository->save($booking);

        expect($fx->repository->get($booking->id)->cancellation())->toBeNull();
    });

    it('counts only the upcoming, slot-holding bookings of a customer', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $now = new DateTimeImmutable('2026-11-02T10:30:00Z');

        $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-01T10:00:00Z', '2026-11-01T11:00:00Z')); // over
        $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z')); // in progress
        $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-03T10:00:00Z', '2026-11-03T11:00:00Z')); // upcoming
        $fx->repository->save(Builders::cancel(Builders::booking($fx->space, $fx->customer, '2026-11-04T10:00:00Z', '2026-11-04T11:00:00Z')));
        $fx->repository->save(Builders::booking($fx->otherSpace, $fx->customer, '2026-11-05T10:00:00Z', '2026-11-05T11:00:00Z')); // other space, still counts

        expect($fx->repository->countUpcomingFor($fx->customer, $now))->toBe(3);
    });

    it('lists a customer\'s bookings, latest first', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $early = Builders::booking($fx->space, $fx->customer, '2026-11-03T10:00:00Z', '2026-11-03T11:00:00Z');
        $late = Builders::booking($fx->otherSpace, $fx->customer, '2026-11-05T10:00:00Z', '2026-11-05T11:00:00Z');
        $fx->repository->save($early);
        $fx->repository->save($late);

        expect(array_map(static fn (Booking $b): string => $b->id->value, $fx->repository->forCustomer($fx->customer)))
            ->toBe([$late->id->value, $early->id->value])
            ->and($fx->repository->forCustomer(new CustomerId(999_999)))->toBe([]);
    });

    it('stores the payment deadline', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $booking = Builders::booking($fx->space, $fx->customer, holdUntil: '2026-11-01T09:15:30.250000-03:00');
        $fx->repository->save($booking);

        expect($fx->repository->get($booking->id)->holdUntil->format('Y-m-d\TH:i:s.uP'))->toBe('2026-11-01T12:15:30.250000+00:00');
    });

    it('finds unpaid bookings whose hold ended before a cutoff, oldest first', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $late = Builders::booking($fx->space, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z', holdUntil: '2026-11-01T12:10:00Z');
        $early = Builders::booking($fx->space, $fx->customer, '2026-11-02T12:00:00Z', '2026-11-02T13:00:00Z', holdUntil: '2026-11-01T12:05:00Z');
        $notYet = Builders::booking($fx->space, $fx->customer, '2026-11-02T14:00:00Z', '2026-11-02T15:00:00Z', holdUntil: '2026-11-01T12:20:00Z');
        $paid = Builders::booking($fx->space, $fx->customer, '2026-11-02T16:00:00Z', '2026-11-02T17:00:00Z', holdUntil: '2026-11-01T12:00:00Z');
        $paid->confirm();
        foreach ([$late, $early, $notYet, $paid] as $booking) {
            $fx->repository->save($booking);
        }
        $ids = static fn (array $bookings): array => array_map(static fn (Booking $b): string => $b->id->value, $bookings);
        $cutoff = new DateTimeImmutable('2026-11-01T12:15:00Z');

        expect($ids($fx->repository->pendingWithHoldEndedBefore($cutoff, 10)))->toBe([$early->id->value, $late->id->value])
            ->and($ids($fx->repository->pendingWithHoldEndedBefore($cutoff, 1)))->toBe([$early->id->value])
            ->and($fx->repository->pendingWithHoldEndedBefore(new DateTimeImmutable('2026-11-01T12:05:00Z'), 10))->toBe([]);
    });

    it('gives the slot of an expired booking to the next customer', function (): void {
        /** @var BookingRepositoryFixture $fx */
        $fx = $this->fx;
        $expired = Builders::booking($fx->space, $fx->customer);
        $fx->repository->save($expired);
        $expired->expire(new DateTimeImmutable('2026-11-01T13:00:00Z'));
        $fx->repository->save($expired);

        $fx->repository->save(Builders::booking($fx->space, $fx->customer));

        expect($fx->repository->findOverlapping($fx->space, $expired->slot))->toHaveCount(1)
            ->and($fx->repository->get($expired->id)->status())->toBe(BookingStatus::Expired);
    });
}
