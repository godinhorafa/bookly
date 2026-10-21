<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

/**
 * Specification pattern: one business rule about a booking request, combinable
 * with and(), or() and not() into larger rules.
 */
interface BookingSpecification
{
    public function isSatisfiedBy(BookingRequest $request): bool;

    /**
     * The rule in plain words, e.g. "Bookings must last at least 30 minutes."
     */
    public function describe(): string;

    /**
     * Descriptions of every rule the request breaks; empty when it is satisfied.
     *
     * @return list<string>
     */
    public function violationsOf(BookingRequest $request): array;

    public function and(self ...$others): self;

    public function or(self ...$others): self;

    public function not(): self;
}
