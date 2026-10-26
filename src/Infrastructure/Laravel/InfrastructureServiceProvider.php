<?php

declare(strict_types=1);

namespace Infrastructure\Laravel;

use Application\Event\EventSubscriber;
use Application\Event\Outbox;
use Application\Event\OutboxRelay;
use Application\Transaction;
use Domain\Booking\BookingRepository;
use Domain\Booking\PaymentHold;
use Domain\Booking\Specification\AlignedToGrid;
use Domain\Booking\Specification\BelowActiveBookingLimit;
use Domain\Booking\Specification\BookingRules;
use Domain\Booking\Specification\DurationBetween;
use Domain\Booking\Specification\StartsWithMinimumNotice;
use Domain\Booking\Specification\WithinBookingHorizon;
use Domain\Booking\Specification\WithinOpeningHours;
use Domain\Payment\PaymentGateway;
use Domain\Payment\PaymentGateways;
use Domain\Payment\PaymentRepository;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\PricingPipeline;
use Domain\Pricing\Rule\CouponDiscount;
use Domain\Pricing\Rule\LongBookingDiscount;
use Domain\Pricing\Rule\PeakHourSurcharge;
use Domain\Shared\Clock;
use Domain\Shared\WeeklyWindow;
use Domain\Space\SpaceRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Clock\SystemClock;
use Infrastructure\Event\PostgresOutbox;
use Infrastructure\Laravel\Console\ExpireUnpaidBookingsCommand;
use Infrastructure\Laravel\Console\RelayOutboxCommand;
use Infrastructure\Notification\AuditTrailSubscriber;
use Infrastructure\Notification\BookingConfirmationEmailSubscriber;
use Infrastructure\Notification\WebhookSubscriber;
use Infrastructure\Payment\FakePaymentGateway;
use Infrastructure\Payment\StripePaymentGateway;
use Infrastructure\Persistence\Eloquent\EloquentBookingRepository;
use Infrastructure\Persistence\Eloquent\EloquentCouponRepository;
use Infrastructure\Persistence\Eloquent\EloquentPaymentRepository;
use Infrastructure\Persistence\Eloquent\EloquentSpaceRepository;
use Infrastructure\Persistence\LaravelTransaction;

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
        PaymentRepository::class => EloquentPaymentRepository::class,
        Transaction::class => LaravelTransaction::class,
        Outbox::class => PostgresOutbox::class,
    ];

    /** @var array<class-string, class-string> */
    public array $singletons = [
        Clock::class => SystemClock::class,
    ];

    public function register(): void
    {
        $this->app->singleton(PricingPipeline::class, fn (): PricingPipeline => $this->pricingPipeline($this->app->make(Config::class)));
        $this->app->singleton(BookingRules::class, fn (): BookingRules => $this->bookingRules($this->app->make(Config::class)));
        $this->app->singleton(PaymentHold::class, fn (): PaymentHold => new PaymentHold($this->app->make(Config::class)->integer('booking.payment_hold_minutes')));
        // The fake keeps its ledger in the default cache store, so charges survive across requests.
        $this->app->singleton(FakePaymentGateway::class, fn (): FakePaymentGateway => new FakePaymentGateway(
            $this->app->make(CacheFactory::class)->store(),
        ));
        $this->app->singleton(PaymentGateways::class, fn (): PaymentGateways => $this->paymentGateways($this->app->make(Config::class)));
        $this->app->bind(OutboxRelay::class, fn (): OutboxRelay => new OutboxRelay(
            $this->app->make(Outbox::class),
            $this->app->make(Clock::class),
            ...$this->eventSubscribers($this->app->make(Config::class)),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RelayOutboxCommand::class, ExpireUnpaidBookingsCommand::class]);
        }
    }

    /**
     * The observers of domain events. Adding a reaction to an event means adding one here.
     *
     * @return list<EventSubscriber>
     */
    private function eventSubscribers(Config $config): array
    {
        $subscribers = [
            $this->app->make(AuditTrailSubscriber::class),
            $this->app->make(BookingConfirmationEmailSubscriber::class),
        ];

        $secret = $config->string('webhooks.secret');
        /** @var list<string> $endpoints */
        $endpoints = $config->array('webhooks.endpoints');

        foreach ($secret === '' ? [] : $endpoints as $url) {
            $subscribers[] = new WebhookSubscriber($this->app->make(Http::class), $this->app->make(Clock::class), $url, $secret);
        }

        return $subscribers;
    }

    /**
     * Every gateway with credentials is registered, so refunds can reach the
     * gateway of an old charge even after the default has changed.
     */
    private function paymentGateways(Config $config): PaymentGateways
    {
        /** @var array<string, PaymentGateway> $gateways */
        $gateways = ['fake' => $this->app->make(FakePaymentGateway::class)];

        $stripeSecret = $config->get('payments.stripe.secret');
        if (is_string($stripeSecret) && $stripeSecret !== '') {
            $gateways['stripe'] = new StripePaymentGateway(
                $this->app->make(Http::class),
                $stripeSecret,
                $config->string('payments.stripe.base_url'),
            );
        }

        $default = $config->string('payments.default');
        $defaultGateway = $gateways[$default] ?? throw new \InvalidArgumentException("Payment gateway \"{$default}\" is not configured.");
        unset($gateways[$default]);

        return new PaymentGateways($defaultGateway, ...array_values($gateways));
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
