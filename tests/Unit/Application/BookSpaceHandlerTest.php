<?php

declare(strict_types=1);

use Application\Booking\BookSpace;
use Application\Booking\BookSpaceHandler;
use Domain\Booking\BookingStatus;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Pricing\Coupon;
use Domain\Pricing\Exception\InvalidCoupon;
use Domain\Pricing\PricingPipeline;
use Domain\Pricing\Rule\CouponDiscount;
use Domain\Pricing\Rule\PeakHourSurcharge;
use Domain\Shared\Exception\InvalidTimeSlot;
use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;
use Domain\Space\Exception\SpaceNotFound;
use Domain\Space\SpaceId;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\InMemoryBookingRepository;
use Tests\Support\InMemory\InMemoryCouponRepository;
use Tests\Support\InMemory\InMemorySpaceRepository;

beforeEach(function (): void {
    $this->spaces = new InMemorySpaceRepository;
    $this->bookings = new InMemoryBookingRepository;
    $this->coupons = new InMemoryCouponRepository;
    $this->clock = new FrozenClock('2026-11-01T12:00:00Z');
    $this->handler = new BookSpaceHandler(
        $this->spaces,
        $this->bookings,
        $this->coupons,
        new PricingPipeline(new PeakHourSurcharge(25, WeeklyWindow::weekdays('18:00', '22:00')), new CouponDiscount),
        $this->clock,
    );

    $this->space = Builders::space(hourlyRate: 8_000);
    $this->spaces->save($this->space);
});

function bookCommand(
    string $spaceId,
    string $start = '2026-11-02T10:00:00-03:00',
    string $end = '2026-11-02T11:30:00-03:00',
    ?string $coupon = null,
): BookSpace {
    return new BookSpace($spaceId, 42, $start, $end, $coupon);
}

it('creates a pending booking priced by the pipeline', function (): void {
    // Monday 17:00-19:00 local: 2h at 80.00, the second hour at peak (+25%)
    $result = $this->handler->handle(bookCommand($this->space->id->value, '2026-11-02T17:00:00-03:00', '2026-11-02T19:00:00-03:00'));

    $booking = $this->bookings->get($result->bookingId);

    expect($booking->status())->toBe(BookingStatus::Pending)
        ->and($booking->customerId->value)->toBe(42)
        ->and($result->quote->base->amount)->toBe(16_000)
        ->and($result->quote->total->amount)->toBe(18_000)
        ->and($booking->price->equals($result->quote->total))->toBeTrue();
});

it('applies a valid coupon, whatever the case the customer typed', function (): void {
    $this->coupons->save(Coupon::percentage('WELCOME10', 10, new DateTimeImmutable('2026-12-31T23:59:59Z')));

    $result = $this->handler->handle(bookCommand($this->space->id->value, coupon: ' welcome10 '));

    expect($result->quote->total->amount)->toBe(10_800);
});

it('rejects unknown or expired coupons before booking anything', function (string $code): void {
    $this->coupons->save(Coupon::percentage('OLDPROMO', 10, new DateTimeImmutable('2026-10-31T00:00:00Z')));

    try {
        $this->handler->handle(bookCommand($this->space->id->value, coupon: $code));
    } finally {
        expect($this->bookings->findOverlapping($this->space->id, TimeSlot::fromStrings('2026-11-02T00:00:00Z', '2026-11-03T00:00:00Z')))->toBe([]);
    }
})->with(['OLDPROMO', 'NOSUCHCODE'])->throws(InvalidCoupon::class);

it('refuses a slot that is already taken', function (): void {
    $this->handler->handle(bookCommand($this->space->id->value));

    $this->handler->handle(bookCommand($this->space->id->value, '2026-11-02T11:00:00-03:00', '2026-11-02T12:00:00-03:00'));
})->throws(SlotUnavailable::class);

it('fails for an unknown space', function (): void {
    $this->handler->handle(bookCommand(SpaceId::generate()->value));
})->throws(SpaceNotFound::class);

it('turns unparseable dates into a domain error', function (): void {
    $this->handler->handle(bookCommand($this->space->id->value, 'tomorrow-ish', '2026-11-02T12:00:00Z'));
})->throws(InvalidTimeSlot::class);
