<?php

declare(strict_types=1);

use Application\Event\Outbox;
use Domain\Booking\CustomerId;
use Domain\Booking\Event\BookingConfirmed;
use Domain\Booking\Event\BookingRequested;
use Domain\Space\SpaceId;
use Tests\Support\Builders;

/**
 * Behaviour every Outbox must have; runs against the in-memory and PostgreSQL outboxes.
 *
 * @param  Closure(): Outbox  $setUp
 */
function outboxContract(Closure $setUp): void
{
    beforeEach(function () use ($setUp): void {
        $this->outbox = $setUp();
        $this->t0 = new DateTimeImmutable('2026-11-01T12:00:00Z');
        $this->event = fn (): BookingRequested => BookingRequested::of(Builders::booking(SpaceId::generate(), new CustomerId(1)));
    });

    it('hands out appended events in order, with their payload', function (): void {
        $first = ($this->event)();
        $second = ($this->event)(); // different bookings, so both are due at once
        $this->outbox->append($this->t0, $first, $second);

        $claimed = $this->outbox->claimDue($this->t0, 10, 60);

        expect(array_map(fn ($e): string => $e->aggregateId, $claimed))->toBe([$first->bookingId, $second->bookingId])
            ->and($claimed[0]->name)->toBe('booking.requested')
            // Compared as a map: jsonb does not keep key order, and consumers must not rely on it.
            ->and($claimed[0]->payload)->toEqual($first->payload())
            ->and($claimed[0]->occurredAt->getTimestamp())->toBe($this->t0->getTimestamp())
            ->and($claimed[0]->attempts)->toBe(0);
    });

    it('leases claimed events so they are not handed out twice', function (): void {
        $this->outbox->append($this->t0, ($this->event)());

        expect($this->outbox->claimDue($this->t0, 10, 60))->toHaveCount(1)
            ->and($this->outbox->claimDue($this->t0->modify('+59 seconds'), 10, 60))->toBe([])
            ->and($this->outbox->claimDue($this->t0->modify('+61 seconds'), 10, 60))->toHaveCount(1); // lease expired
    });

    it('respects the batch size', function (): void {
        $this->outbox->append($this->t0, ($this->event)(), ($this->event)(), ($this->event)());

        expect($this->outbox->claimDue($this->t0, 2, 60))->toHaveCount(2)
            ->and($this->outbox->claimDue($this->t0, 2, 60))->toHaveCount(1);
    });

    it('never hands out published or dead events again', function (): void {
        $this->outbox->append($this->t0, ($this->event)(), ($this->event)());
        [$a, $b] = $this->outbox->claimDue($this->t0, 10, 60);

        $this->outbox->markPublished($a->id, $this->t0);
        $this->outbox->markDead($b->id, 'boom', $this->t0);

        expect($this->outbox->claimDue($this->t0->modify('+1 day'), 10, 60))->toBe([]);
    });

    it('retries at the scheduled time and counts attempts', function (): void {
        $this->outbox->append($this->t0, ($this->event)());
        [$event] = $this->outbox->claimDue($this->t0, 10, 60);

        $this->outbox->scheduleRetry($event->id, 'webhook down', $this->t0->modify('+30 seconds'));

        expect($this->outbox->claimDue($this->t0->modify('+29 seconds'), 10, 1))->toBe([])
            ->and($this->outbox->claimDue($this->t0->modify('+30 seconds'), 10, 1)[0]->attempts)->toBe(1);
    });

    it('remembers which subscribers already handled an event', function (): void {
        $this->outbox->append($this->t0, ($this->event)());
        [$event] = $this->outbox->claimDue($this->t0, 10, 60);

        $this->outbox->markDelivered($event->id, 'audit-trail', $this->t0);
        $this->outbox->markDelivered($event->id, 'audit-trail', $this->t0); // idempotent
        $this->outbox->markDelivered($event->id, 'email', $this->t0);

        expect($this->outbox->deliveredTo($event->id))->toEqualCanonicalizing(['audit-trail', 'email']);
    });

    it('holds back later events of a booking until the earlier ones are out', function (): void {
        $booking = Builders::booking(SpaceId::generate(), new CustomerId(1));
        $requested = BookingRequested::of($booking);
        $booking->confirm();
        $confirmed = BookingConfirmed::of($booking);
        $this->outbox->append($this->t0, $requested, $confirmed);

        $claimed = $this->outbox->claimDue($this->t0, 10, 60);
        expect(array_map(fn ($e): string => $e->name, $claimed))->toBe(['booking.requested'])
            ->and($this->outbox->claimDue($this->t0, 10, 60))->toBe([]); // confirmed waits behind the leased one

        $this->outbox->scheduleRetry($claimed[0]->id, 'webhook down', $this->t0);
        $retried = $this->outbox->claimDue($this->t0, 10, 60);
        expect(array_map(fn ($e): string => $e->name, $retried))->toBe(['booking.requested']); // still alone, still first

        $this->outbox->markPublished($retried[0]->id, $this->t0);
        expect($this->outbox->claimDue($this->t0, 10, 60)[0]->name)->toBe('booking.confirmed');
    });
}
