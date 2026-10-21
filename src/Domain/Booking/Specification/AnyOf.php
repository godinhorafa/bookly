<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

/**
 * Satisfied when at least one part is.
 */
final readonly class AnyOf extends CompositeSpecification
{
    /** @var list<BookingSpecification> */
    private array $parts;

    public function __construct(BookingSpecification ...$parts)
    {
        $this->parts = array_values($parts);
    }

    public function isSatisfiedBy(BookingRequest $request): bool
    {
        foreach ($this->parts as $part) {
            if ($part->isSatisfiedBy($request)) {
                return true;
            }
        }

        return false;
    }

    public function describe(): string
    {
        return 'One of: ' . implode(' / ', array_map(static fn (BookingSpecification $p): string => rtrim($p->describe(), '.'), $this->parts)) . '.';
    }
}
