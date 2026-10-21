<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

/**
 * Gives every specification the combinators and the default "one violation" behaviour.
 */
abstract readonly class CompositeSpecification implements BookingSpecification
{
    public function violationsOf(BookingRequest $request): array
    {
        return $this->isSatisfiedBy($request) ? [] : [$this->describe()];
    }

    public function and(BookingSpecification ...$others): BookingSpecification
    {
        return new AllOf($this, ...$others);
    }

    public function or(BookingSpecification ...$others): BookingSpecification
    {
        return new AnyOf($this, ...$others);
    }

    public function not(): BookingSpecification
    {
        return new Not($this);
    }
}
