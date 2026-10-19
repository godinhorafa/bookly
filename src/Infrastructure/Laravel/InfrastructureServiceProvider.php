<?php

declare(strict_types=1);

namespace Infrastructure\Laravel;

use Domain\Booking\BookingRepository;
use Domain\Space\SpaceRepository;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Persistence\Eloquent\EloquentBookingRepository;
use Infrastructure\Persistence\Eloquent\EloquentSpaceRepository;

/**
 * Binds domain ports to their infrastructure adapters.
 */
final class InfrastructureServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        BookingRepository::class => EloquentBookingRepository::class,
        SpaceRepository::class => EloquentSpaceRepository::class,
    ];
}
