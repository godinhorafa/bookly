<?php

declare(strict_types=1);

use Application\Booking\ExpireUnpaidBookingsHandler;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Space\SpaceId;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;
use Tests\Support\InlineTransaction;
use Tests\Support\InMemory\InMemoryBookingRepository;
use Tests\Support\InMemory\InMemoryOutbox;

beforeEach(function (): void {
    $this->bookings = new InMemoryBookingRepository;
    $this->outbox = new InMemoryOutbox;
    $this->clock = new FrozenClock('2026-11-01T12:00:00Z');
    $this->handler = new ExpireUnpaidBookingsHandler($this->bookings, $this->outbox, new InlineTransaction, $this->clock);

    $this->space = SpaceId::generate();
    $this->unpaid = Builders::booking($this->space, new CustomerId(1), holdUntil: '2026-11-01T12:15:00Z');
    $this->bookings->save($this->unpaid);
});

it('leaves unpaid bookings alone during the grace period after the deadline', function (): void {
    // Two minutes of grace: a payment that started before 12:15 always finishes first.
    $this->clock->travelTo('2026-11-01T12:17:00Z');

    expect($this->handler->handle(limit: 10))->toBe(0)
        ->and($this->bookings->get($this->unpaid->id)->status())->toBe(BookingStatus::Pending);
});

it('expires them after the grace period, recording booking.expired', function (): void {
    $this->clock->travelTo('2026-11-01T12:17:01Z');

    expect($this->handler->handle(limit: 10))->toBe(1)
        ->and($this->bookings->get($this->unpaid->id)->status())->toBe(BookingStatus::Expired)
        ->and($this->outbox->names())->toBe(['booking.expired']);
});

it('frees the slot for the next customer', function (): void {
    $this->clock->travelTo('2026-11-01T13:00:00Z');
    $this->handler->handle(limit: 10);

    $this->bookings->save(Builders::booking($this->space, new CustomerId(2)));

    expect($this->bookings->findOverlapping($this->space, $this->unpaid->slot))->toHaveCount(1);
});

it('never touches paid bookings', function (): void {
    $paid = Builders::booking($this->space, new CustomerId(2), '2026-11-02T14:00:00Z', '2026-11-02T15:00:00Z', holdUntil: '2026-11-01T12:15:00Z');
    $paid->confirm();
    $this->bookings->save($paid);
    $this->clock->travelTo('2026-11-01T13:00:00Z');

    $this->handler->handle(limit: 10);

    expect($this->bookings->get($paid->id)->status())->toBe(BookingStatus::Confirmed);
});

it('expires at most the given number of bookings per run', function (): void {
    foreach (['14', '16', '18'] as $hour) {
        $this->bookings->save(Builders::booking($this->space, new CustomerId(1), "2026-11-02T{$hour}:00:00Z", "2026-11-02T{$hour}:30:00Z", holdUntil: '2026-11-01T12:15:00Z'));
    }
    $this->clock->travelTo('2026-11-01T13:00:00Z');

    expect($this->handler->handle(limit: 3))->toBe(3)
        ->and($this->handler->handle(limit: 10))->toBe(1);
});
