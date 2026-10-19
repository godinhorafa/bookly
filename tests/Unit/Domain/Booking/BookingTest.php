<?php

declare(strict_types=1);

use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\InvalidBookingTransition;
use Domain\Space\SpaceId;
use Tests\Support\Builders;

beforeEach(function (): void {
    $this->space = SpaceId::generate();
    $this->customer = new CustomerId(7);
});

it('starts as pending and holds its slot', function (): void {
    $booking = Builders::booking($this->space, $this->customer);

    expect($booking->status())->toBe(BookingStatus::Pending)
        ->and($booking->holdsSlot())->toBeTrue();
});

it('can be confirmed and then cancelled', function (): void {
    $booking = Builders::booking($this->space, $this->customer);

    $booking->confirm();
    expect($booking->status())->toBe(BookingStatus::Confirmed);

    $booking->cancel();
    expect($booking->status())->toBe(BookingStatus::Cancelled)
        ->and($booking->holdsSlot())->toBeFalse();
});

it('can be cancelled while pending, e.g. when payment fails', function (): void {
    $booking = Builders::booking($this->space, $this->customer);

    $booking->cancel();

    expect($booking->status())->toBe(BookingStatus::Cancelled);
});

it('rejects invalid transitions', function (Closure $act): void {
    $booking = Builders::booking($this->space, $this->customer);

    $act($booking);
})->with([
    'confirm twice' => [function ($b): void {
        $b->confirm();
        $b->confirm();
    }],
    'cancel twice' => [function ($b): void {
        $b->cancel();
        $b->cancel();
    }],
    'confirm after cancel' => [function ($b): void {
        $b->cancel();
        $b->confirm();
    }],
])->throws(InvalidBookingTransition::class);

it('conflicts only with active, overlapping bookings of the same space', function (): void {
    $booking = Builders::booking($this->space, $this->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
    $overlapping = Builders::booking($this->space, $this->customer, '2026-11-02T10:30:00Z', '2026-11-02T11:30:00Z');
    $adjacent = Builders::booking($this->space, $this->customer, '2026-11-02T11:00:00Z', '2026-11-02T12:00:00Z');
    $elsewhere = Builders::booking(SpaceId::generate(), $this->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
    $cancelled = Builders::booking($this->space, $this->customer, '2026-11-02T10:00:00Z', '2026-11-02T11:00:00Z');
    $cancelled->cancel();

    expect($booking->conflictsWith($overlapping))->toBeTrue()
        ->and($booking->conflictsWith($adjacent))->toBeFalse()
        ->and($booking->conflictsWith($elsewhere))->toBeFalse()
        ->and($booking->conflictsWith($cancelled))->toBeFalse()
        ->and($booking->conflictsWith($booking))->toBeFalse();
});

it('keeps the slot-holding rule in sync with the database constraint', function (): void {
    expect(array_map(static fn (BookingStatus $s): bool => $s->holdsSlot(), BookingStatus::cases()))
        ->toBe([true, true, false]);
});
