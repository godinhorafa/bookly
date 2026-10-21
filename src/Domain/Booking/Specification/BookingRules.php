<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

use Domain\Booking\Exception\BookingRulesViolated;

/**
 * The set of rules a booking request must satisfy, assembled from configuration.
 */
final readonly class BookingRules
{
    public function __construct(private BookingSpecification $specification) {}

    public function assertSatisfiedBy(BookingRequest $request): void
    {
        $violations = $this->specification->violationsOf($request);

        if ($violations !== []) {
            throw BookingRulesViolated::because($violations);
        }
    }
}
