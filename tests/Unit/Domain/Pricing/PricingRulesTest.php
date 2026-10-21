<?php

declare(strict_types=1);

use Domain\Booking\TimeSlot;
use Domain\Pricing\AdjustmentKind;
use Domain\Pricing\Coupon;
use Domain\Pricing\Exception\InvalidPricingRule;
use Domain\Pricing\PricingContext;
use Domain\Pricing\Quote;
use Domain\Pricing\Rule\CouponDiscount;
use Domain\Pricing\Rule\LongBookingDiscount;
use Domain\Pricing\Rule\PeakHourSurcharge;
use Domain\Pricing\Rule\PeakWindow;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Tests\Support\Builders;

function pricingContext(string $start, string $end, ?Coupon $coupon = null): PricingContext
{
    return new PricingContext(Builders::space(hourlyRate: 6_000), TimeSlot::fromStrings($start, $end), $coupon);
}

function baseQuote(PricingContext $context): Quote
{
    return Quote::startingAt($context->space->basePriceFor($context->slot));
}

describe('peak hour surcharge', function (): void {
    it('charges the percentage on peak minutes only', function (): void {
        // Monday 17:00-19:30 local: 150 min, 90 of them after 18:00
        $context = pricingContext('2026-11-02T17:00:00-03:00', '2026-11-02T19:30:00-03:00');
        $rule = new PeakHourSurcharge(25, PeakWindow::weekdays('18:00', '22:00'));

        $quote = $rule->apply(baseQuote($context), $context);

        expect($quote->base->amount)->toBe(15_000)
            ->and($quote->adjustments)->toHaveCount(1)
            ->and($quote->adjustments[0]->kind)->toBe(AdjustmentKind::Surcharge)
            ->and($quote->adjustments[0]->amount->amount)->toBe(2_250) // 60.00/h * 1.5h * 25%
            ->and($quote->adjustments[0]->description)->toBe('Peak hours (+25% on 90 min)')
            ->and($quote->total->amount)->toBe(17_250);
    });

    it('adds no line when nothing falls in a peak window', function (): void {
        $context = pricingContext('2026-11-02T09:00:00-03:00', '2026-11-02T11:00:00-03:00');
        $rule = new PeakHourSurcharge(25, PeakWindow::weekdays('18:00', '22:00'));

        $quote = $rule->apply(baseQuote($context), $context);

        expect($quote->adjustments)->toBe([])
            ->and($quote->total->amount)->toBe(12_000);
    });

    it('validates the percentage', function (int $percent): void {
        new PeakHourSurcharge($percent, PeakWindow::weekdays('18:00', '22:00'));
    })->with([0, 101])->throws(InvalidPricingRule::class);
});

describe('long booking discount', function (): void {
    it('discounts bookings at or above the threshold', function (string $end, int $expectedTotal): void {
        $context = pricingContext('2026-11-02T08:00:00-03:00', $end);

        $quote = (new LongBookingDiscount(240, 10))->apply(baseQuote($context), $context);

        expect($quote->total->amount)->toBe($expectedTotal);
    })->with([
        'exactly 4h' => ['2026-11-02T12:00:00-03:00', 21_600],
        '3h59' => ['2026-11-02T11:59:00-03:00', 23_900],
    ]);

    it('validates its configuration', function (int $minutes, int $percent): void {
        new LongBookingDiscount($minutes, $percent);
    })->with([
        'zero minutes' => [0, 10],
        'zero percent' => [240, 0],
    ])->throws(InvalidPricingRule::class);
});

describe('coupon discount', function (): void {
    it('does nothing without a coupon', function (): void {
        $context = pricingContext('2026-11-02T08:00:00-03:00', '2026-11-02T09:00:00-03:00');

        expect((new CouponDiscount)->apply(baseQuote($context), $context)->adjustments)->toBe([]);
    });

    it('applies the coupon to the running total', function (): void {
        $context = pricingContext('2026-11-02T08:00:00-03:00', '2026-11-02T09:00:00-03:00', Coupon::percentage('welcome15', 15));

        $quote = (new CouponDiscount)->apply(baseQuote($context), $context);

        expect($quote->adjustments[0]->description)->toBe('Coupon WELCOME15')
            ->and($quote->total->amount)->toBe(5_100);
    });

    it('never makes the price negative', function (): void {
        $context = pricingContext('2026-11-02T08:00:00-03:00', '2026-11-02T09:00:00-03:00', Coupon::fixed('BIGGIFT', Money::of(99_999, Currency::BRL)));

        expect((new CouponDiscount)->apply(baseQuote($context), $context)->total->isZero())->toBeTrue();
    });
});
