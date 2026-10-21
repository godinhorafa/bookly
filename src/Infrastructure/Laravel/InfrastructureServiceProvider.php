<?php

declare(strict_types=1);

namespace Infrastructure\Laravel;

use Domain\Booking\BookingRepository;
use Domain\Booking\Specification\AlignedToGrid;
use Domain\Booking\Specification\BelowActiveBookingLimit;
use Domain\Booking\Specification\BookingRules;
use Domain\Booking\Specification\DurationBetween;
use Domain\Booking\Specification\StartsWithMinimumNotice;
use Domain\Booking\Specification\WithinBookingHorizon;
use Domain\Booking\Specification\WithinOpeningHours;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\PricingPipeline;
use Domain\Pricing\Rule\CouponDiscount;
use Domain\Pricing\Rule\LongBookingDiscount;
use Domain\Pricing\Rule\PeakHourSurcharge;
use Domain\Shared\Clock;
use Domain\Shared\WeeklyWindow;
use Domain\Space\SpaceRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Clock\SystemClock;
use Infrastructure\Persistence\Eloquent\EloquentBookingRepository;
use Infrastructure\Persistence\Eloquent\EloquentCouponRepository;
use Infrastructure\Persistence\Eloquent\EloquentSpaceRepository;

/**
 * Binds domain ports to their infrastructure adapters and assembles
 * domain services from configuration.
 */
final class InfrastructureServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        BookingRepository::class => EloquentBookingRepository::class,
        SpaceRepository::class => EloquentSpaceRepository::class,
        CouponRepository::class => EloquentCouponRepository::class,
    ];

    /** @var array<class-string, class-string> */
    public array $singletons = [
        Clock::class => SystemClock::class,
    ];

    public function register(): void
    {
        $this->app->singleton(PricingPipeline::class, fn (): PricingPipeline => $this->pricingPipeline($this->app->make(Config::class)));
        $this->app->singleton(BookingRules::class, fn (): BookingRules => $this->bookingRules($this->app->make(Config::class)));
    }

    private function bookingRules(Config $config): BookingRules
    {
        return new BookingRules(
            (new StartsWithMinimumNotice($config->integer('booking.minimum_notice_minutes')))->and(
                new WithinBookingHorizon($config->integer('booking.horizon_days')),
                new DurationBetween(
                    $config->integer('booking.minimum_duration_minutes'),
                    $config->integer('booking.maximum_duration_minutes'),
                ),
                new AlignedToGrid($config->integer('booking.grid_minutes')),
                new WithinOpeningHours,
                new BelowActiveBookingLimit($config->integer('booking.max_upcoming_per_customer')),
            ),
        );
    }

    private function pricingPipeline(Config $config): PricingPipeline
    {
        /** @var list<array{0: list<int>, 1: string, 2: string}> $windows */
        $windows = $config->array('pricing.peak_hours.windows');

        return new PricingPipeline(
            new PeakHourSurcharge(
                $config->integer('pricing.peak_hours.surcharge_percent'),
                ...array_map(static fn (array $w): WeeklyWindow => new WeeklyWindow(...$w), $windows),
            ),
            new LongBookingDiscount(
                $config->integer('pricing.long_booking.minimum_minutes'),
                $config->integer('pricing.long_booking.discount_percent'),
            ),
            new CouponDiscount,
        );
    }
}
