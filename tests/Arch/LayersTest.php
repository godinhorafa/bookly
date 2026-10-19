<?php

declare(strict_types=1);

use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\TimeSlot;
use Domain\Shared\Exception\DomainException;
use Domain\Shared\Money;
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

arch('repositories are ports owned by the domain')
    ->expect([BookingRepository::class, SpaceRepository::class])
    ->toBeInterfaces();

arch('eloquent stays inside infrastructure')
    ->expect('Illuminate\Database')
    ->toOnlyBeUsedIn(['Infrastructure', 'App', 'Database', 'Tests']);

arch('domain exceptions share a common base')
    ->expect(['Domain\Shared\Exception', 'Domain\Booking\Exception', 'Domain\Space\Exception'])
    ->classes()
    ->toExtend(DomainException::class)
    ->ignoring(DomainException::class);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
