<?php

declare(strict_types=1);

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingStatus;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Booking\TimeSlot;
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

        $first->cancel();
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
        $cancelled->cancel();
        $elsewhere = Builders::booking($fx->otherSpace, $fx->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
        $outside = Builders::booking($fx->space, $fx->customer, '2026-11-02T16:00:00Z', '2026-11-02T17:00:00Z');

        foreach ([$late, $early, $cancelled, $elsewhere, $outside] as $booking) {
            $fx->repository->save($booking);
        }

        $found = $fx->repository->findOverlapping($fx->space, TimeSlot::fromStrings('2026-11-02T10:00:00Z', '2026-11-02T15:30:00Z'));

        expect(array_map(static fn (Booking $b): string => $b->id->value, $found))
            ->toBe([$early->id->value, $late->id->value]);
    });
}
