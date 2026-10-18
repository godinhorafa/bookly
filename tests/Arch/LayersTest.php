<?php

declare(strict_types=1);
use Domain\Booking\TimeSlot;
use Domain\Shared\Exception\DomainException;
use Domain\Shared\Money;

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
    ->expect([Money::class, TimeSlot::class])
    ->toBeReadonly()
    ->toBeFinal();

arch('domain exceptions share a common base')
    ->expect(['Domain\Shared\Exception', 'Domain\Booking\Exception'])
    ->classes()
    ->toExtend(DomainException::class)
    ->ignoring(DomainException::class);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
