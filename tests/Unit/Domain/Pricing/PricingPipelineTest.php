<?php

declare(strict_types=1);

use Domain\Booking\TimeSlot;
use Domain\Pricing\Adjustment;
use Domain\Pricing\AdjustmentKind;
use Domain\Pricing\Coupon;
use Domain\Pricing\PricingContext;
use Domain\Pricing\PricingPipeline;
use Domain\Pricing\Quote;
use Domain\Pricing\Rule\CouponDiscount;
use Domain\Pricing\Rule\LongBookingDiscount;
use Domain\Pricing\Rule\PeakHourSurcharge;
use Domain\Pricing\Rule\PeakWindow;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Tests\Support\Builders;

beforeEach(function (): void {
    // Monday 18:00-22:00 local: 4h, all of it peak. Base 4 x 60.00 = 240.00
    $this->context = new PricingContext(
        Builders::space(hourlyRate: 6_000),
        TimeSlot::fromStrings('2026-11-02T18:00:00-03:00', '2026-11-02T22:00:00-03:00'),
        Coupon::fixed('MINUS50', Money::of(5_000, Currency::BRL)),
    );
    $this->peak = new PeakHourSurcharge(25, PeakWindow::weekdays('18:00', '22:00'));
    $this->long = new LongBookingDiscount(240, 10);
    $this->coupon = new CouponDiscount;
});

it('starts from the base price when there are no rules', function (): void {
    expect((new PricingPipeline)->quote($this->context)->total->amount)->toBe(24_000);
});

it('applies surcharges first, then discounts on the running total', function (): void {
    $quote = (new PricingPipeline($this->peak, $this->long, $this->coupon))->quote($this->context);

    expect(array_map(fn (Adjustment $a): array => [$a->rule, $a->amount->amount], $quote->adjustments))->toBe([
        ['peak_hours', 6_000],    // 240.00 + 25%      = 300.00
        ['long_booking', 3_000],  // 300.00 - 10%      = 270.00
        ['coupon', 5_000],        // 270.00 - 50.00    = 220.00
    ])->and($quote->total->amount)->toBe(22_000);
});

it('gives a different price in a different order, which is why the order is explicit', function (): void {
    $quote = (new PricingPipeline($this->coupon, $this->long, $this->peak))->quote($this->context);

    // 240 - 50 = 190; -10% = 171; +60 = 231
    expect($quote->total->amount)->toBe(23_100);
});

it('keeps quotes immutable and skips zero-value lines', function (): void {
    $start = Quote::startingAt(Money::of(1_000, Currency::BRL));
    $next = $start->with(new Adjustment('x', 'nothing', AdjustmentKind::Discount, Money::zero(Currency::BRL)));
    $discounted = $start->with(new Adjustment('y', 'ten off', AdjustmentKind::Discount, Money::of(100, Currency::BRL)));

    expect($next)->toBe($start)
        ->and($start->total->amount)->toBe(1_000)
        ->and($discounted->total->amount)->toBe(900)
        ->and($discounted->adjustments)->toHaveCount(1);
});
