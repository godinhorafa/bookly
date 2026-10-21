<?php

declare(strict_types=1);

namespace Application\Booking;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Booking\TimeSlot;
use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\PricingContext;
use Domain\Pricing\PricingPipeline;
use Domain\Shared\Clock;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;

final readonly class BookSpaceHandler
{
    public function __construct(
        private SpaceRepository $spaces,
        private BookingRepository $bookings,
        private CouponRepository $coupons,
        private PricingPipeline $pricing,
        private Clock $clock,
    ) {}

    public function handle(BookSpace $command): BookSpaceResult
    {
        $space = $this->spaces->get(SpaceId::fromString($command->spaceId));
        $slot = TimeSlot::fromStrings($command->start, $command->end);
        $coupon = $this->resolveCoupon($command->couponCode);

        // Fast path for a friendly error. It is not race-free on its own:
        // BookingRepository::save() enforces the invariant atomically.
        if ($this->bookings->findOverlapping($space->id, $slot) !== []) {
            throw SlotUnavailable::for($space->id, $slot);
        }

        $quote = $this->pricing->quote(new PricingContext($space, $slot, $coupon));

        $booking = Booking::request(
            BookingId::generate(),
            $space->id,
            new CustomerId($command->customerId),
            $slot,
            $quote->total,
        );

        $this->bookings->save($booking);

        return new BookSpaceResult($booking->id, $quote);
    }

    private function resolveCoupon(?string $code): ?Coupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $coupon = $this->coupons->get(Coupon::normaliseCode($code));
        $coupon->assertUsableAt($this->clock->now());

        return $coupon;
    }
}
