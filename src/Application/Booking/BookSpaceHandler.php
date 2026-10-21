<?php

declare(strict_types=1);

namespace Application\Booking;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Booking\Specification\BookingRequest;
use Domain\Booking\Specification\BookingRules;
use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\PricingContext;
use Domain\Pricing\PricingPipeline;
use Domain\Shared\Clock;
use Domain\Shared\TimeSlot;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;

final readonly class BookSpaceHandler
{
    public function __construct(
        private SpaceRepository $spaces,
        private BookingRepository $bookings,
        private CouponRepository $coupons,
        private PricingPipeline $pricing,
        private BookingRules $rules,
        private Clock $clock,
    ) {}

    public function handle(BookSpace $command): BookSpaceResult
    {
        $space = $this->spaces->get(SpaceId::fromString($command->spaceId));
        $slot = TimeSlot::fromStrings($command->start, $command->end);
        $customerId = new CustomerId($command->customerId);
        $now = $this->clock->now();

        // The active-booking count is read before the insert, so the per-customer
        // limit is a soft cap under concurrent requests (see ADR 0005).
        $this->rules->assertSatisfiedBy(new BookingRequest(
            $space,
            $slot,
            $customerId,
            $now,
            $this->bookings->countUpcomingFor($customerId, $now),
        ));

        $coupon = $this->resolveCoupon($command->couponCode, $now);

        // Fast path for a friendly error. It is not race-free on its own:
        // BookingRepository::save() enforces the invariant atomically.
        if ($this->bookings->findOverlapping($space->id, $slot) !== []) {
            throw SlotUnavailable::for($space->id, $slot);
        }

        $quote = $this->pricing->quote(new PricingContext($space, $slot, $coupon));

        $booking = Booking::request(
            BookingId::generate(),
            $space->id,
            $customerId,
            $slot,
            $quote->total,
        );

        $this->bookings->save($booking);

        return new BookSpaceResult($booking->id, $quote);
    }

    private function resolveCoupon(?string $code, \DateTimeImmutable $now): ?Coupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $coupon = $this->coupons->get(Coupon::normaliseCode($code));
        $coupon->assertUsableAt($now);

        return $coupon;
    }
}
