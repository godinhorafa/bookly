<?php

declare(strict_types=1);

namespace Domain\Booking\Specification;

/**
 * Satisfied when every part is. Reports the violations of all failing parts,
 * so a customer sees every problem at once instead of fixing them one by one.
 */
final readonly class AllOf extends CompositeSpecification
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
            if (! $part->isSatisfiedBy($request)) {
                return false;
            }
        }

        return true;
    }

    public function describe(): string
    {
        return implode(' ', array_map(static fn (BookingSpecification $p): string => $p->describe(), $this->parts));
    }

    public function violationsOf(BookingRequest $request): array
    {
        return array_merge(...array_map(
            static fn (BookingSpecification $p): array => $p->violationsOf($request),
            $this->parts,
        ));
    }
}
