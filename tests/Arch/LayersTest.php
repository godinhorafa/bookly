<?php

declare(strict_types=1);

use Application\Event\EventSubscriber;
use Application\Transaction;
use Domain\Booking\BookingCancellation;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Specification\BookingRequest;
use Domain\Booking\Specification\BookingRules;
use Domain\Booking\Specification\BookingSpecification;
use Domain\Cancellation\RefundTier;
use Domain\Cancellation\TieredRefundPolicy;
use Domain\Payment\PaymentGateway;
use Domain\Payment\PaymentRepository;
use Domain\Pricing\Adjustment;
use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Pricing\PricingRule;
use Domain\Pricing\Quote;
use Domain\Shared\Clock;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Exception\DomainException;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;
use Infrastructure\Notification\BookingConfirmedMail;
use Infrastructure\Notification\WebhookSignature;

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

arch('booking rules are composable specifications')
    ->expect('Domain\Booking\Specification')
    ->classes()
    ->toImplement(BookingSpecification::class)
    ->ignoring([BookingRequest::class, BookingRules::class]);

arch('specifications are immutable')
    ->expect('Domain\Booking\Specification')
    ->classes()
    ->toBeReadonly();

arch('payment providers are adapters behind the PaymentGateway port')
    ->expect('Infrastructure\Payment')
    ->classes()
    ->toImplement(PaymentGateway::class);

arch('HTTP clients stay in infrastructure')
    ->expect('Illuminate\Http')
    ->toOnlyBeUsedIn(['Infrastructure', 'App', 'Tests']);

arch('domain events are immutable, serialisable facts')
    ->expect('Domain\Booking\Event')
    ->classes()
    ->toImplement(DomainEvent::class)
    ->toBeReadonly()
    ->toBeFinal();

arch('notification subscribers observe stored events')
    ->expect('Infrastructure\Notification')
    ->classes()
    ->toImplement(EventSubscriber::class)
    ->ignoring([BookingConfirmedMail::class, WebhookSignature::class]);

arch('the domain reads time through the Clock port only')
    ->expect('Domain')
    ->not->toUse(['time', 'microtime', 'date', 'now']);

arch('repositories are ports owned by the domain')
    ->expect([BookingRepository::class, SpaceRepository::class, CouponRepository::class, Clock::class, PaymentRepository::class, PaymentGateway::class, Transaction::class])
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
        'Domain\Payment\Exception',
    ])
    ->classes()
    ->toExtend(DomainException::class)
    ->ignoring(DomainException::class);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
