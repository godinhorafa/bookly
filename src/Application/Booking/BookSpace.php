<?php

declare(strict_types=1);

namespace Application\Booking;

/**
 * Input of the "book a space" use case. Plain primitives, so any delivery
 * mechanism (HTTP, CLI, queue) can build it without knowing domain types.
 */
final readonly class BookSpace
{
    public function __construct(
        public string $spaceId,
        public int $customerId,
        public string $start,
        public string $end,
    ) {}
}
