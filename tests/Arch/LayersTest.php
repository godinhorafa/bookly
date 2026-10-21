<?php

declare(strict_types=1);

use Domain\Booking\BookingCancellation;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Cancellation\RefundTier;
use Domain\Cancellation\TieredRefundPolicy;
use Domain\Pricing\Adjustment;
use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\PricingRule;
use Domain\Pricing\Quote;
use Domain\Shared\Clock;
use Domain\Shared\Exception\DomainException;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;

/*
 * Dependency rule: Domain <- Application <- Infrastructure / App (HTTP).
 * These tests fail the build if a layer reaches outward.
 */

arch('domain is framework agnostic')
    ->expect('Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'App', 'Application', 'Infrastructure', 'Carbon']);

arch('application layer depends only on the domain')
    ->expect('Application')
    ->not->toUse(['Illuminate', 'Laravel', 'App', 'Infrastructure']);

arch('domain code uses strict types')
    ->expect('Domain')
    ->toUseStrictTypes();

arch('value objects are immutable')
    ->expect([Money::class, TimeSlot::class, BookingId::class, CustomerId::class, SpaceId::class, Space::class])
    ->toBeReadonly()
    ->toBeFinal();

arch('pricing rules are interchangeable strategies')
    ->expect('Domain\Pricing\Rule')
    ->classes()
    ->toImplement(PricingRule::class);

arch('pricing and cancellation value objects are immutable')
    ->expect([
        Quote::class,
        Adjustment::class,
        Coupon::class,
        WeeklyWindow::class,
        RefundTier::class,
        TieredRefundPolicy::class,
        BookingCancellation::class,
    ])
    ->toBeReadonly()
    ->toBeFinal();

arch('the domain reads time through the Clock port only')
    ->expect('Domain')
    ->not->toUse(['time', 'microtime', 'date', 'now']);

arch('repositories are ports owned by the domain')
    ->expect([BookingRepository::class, SpaceRepository::class, CouponRepository::class, Clock::class])
    ->toBeInterfaces();

arch('eloquent stays inside infrastructure')
    ->expect('Illuminate\Database')
    ->toOnlyBeUsedIn(['Infrastructure', 'App', 'Database', 'Tests']);

arch('domain exceptions share a common base')
    ->expect([
        'Domain\Shared\Exception',
        'Domain\Booking\Exception',
        'Domain\Space\Exception',
        'Domain\Pricing\Exception',
        'Domain\Cancellation\Exception',
    ])
    ->classes()
    ->toExtend(DomainException::class)
    ->ignoring(DomainException::class);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
