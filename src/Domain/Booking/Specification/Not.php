<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

final readonly class Not extends CompositeSpecification
{
    public function __construct(private BookingSpecification $inner) {}

    public function isSatisfiedBy(BookingRequest $request): bool
    {
        return ! $this->inner->isSatisfiedBy($request);
    }

    public function describe(): string
    {
        return 'Not: ' . $this->inner->describe();
    }
}
