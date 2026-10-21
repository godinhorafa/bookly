<?php

declare(strict_types=1);

use Application\Booking\CancelBooking;
use Application\Booking\CancelBookingHandler;
use Domain\Booking\BookingId;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingAlreadyStarted;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Cancellation\CancellationPolicyType;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\InMemoryBookingRepository;
use Tests\Support\InMemory\InMemorySpaceRepository;

beforeEach(function (): void {
    $this->spaces = new InMemorySpaceRepository;
    $this->bookings = new InMemoryBookingRepository;
    $this->clock = new FrozenClock;
    $this->handler = new CancelBookingHandler($this->bookings, $this->spaces, $this->clock);

    $this->owner = new CustomerId(42);
});

function confirmedBookingIn(CancellationPolicyType $policy): BookingId
{
    $space = Builders::space(policy: $policy);
    test()->spaces->save($space);

    // 2026-11-02T10:00Z -> 11:00Z, BRL 50.00
    $booking = Builders::booking($space->id, test()->owner);
    $booking->confirm();
    test()->bookings->save($booking);

    return $booking->id;
}

it('uses the space policy to compute the refund', function (CancellationPolicyType $policy, int $expected): void {
    $id = confirmedBookingIn($policy);
    $this->clock->travelTo('2026-11-01T04:00:00Z'); // 30h before

    $refund = $this->handler->handle(new CancelBooking($id->value, 42));

    $stored = $this->bookings->get($id);
    expect($refund->amount)->toBe($expected)
        ->and($stored->status())->toBe(BookingStatus::Cancelled)
        ->and($stored->cancellation()?->refund->amount)->toBe($expected);
})->with([
    'flexible' => [CancellationPolicyType::Flexible, 5_000],
    'moderate' => [CancellationPolicyType::Moderate, 2_500],
    'strict' => [CancellationPolicyType::Strict, 0],
]);

it('hides bookings that belong to someone else', function (): void {
    $id = confirmedBookingIn(CancellationPolicyType::Flexible);

    $this->handler->handle(new CancelBooking($id->value, 999));
})->throws(BookingNotFound::class);

it('refuses to cancel after the start', function (): void {
    $id = confirmedBookingIn(CancellationPolicyType::Flexible);
    $this->clock->travelTo('2026-11-02T10:15:00Z');

    $this->handler->handle(new CancelBooking($id->value, 42));
})->throws(BookingAlreadyStarted::class);
