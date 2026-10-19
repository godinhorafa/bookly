<?php

declare(strict_types=1);

namespace Domain\Booking;

use Domain\Shared\Exception\InvalidIdentifier;

/**
 * Reference to the customer who owns a booking. Customers live in the
 * authentication context (Laravel users), so only their id crosses the boundary.
 */
final readonly class CustomerId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw InvalidIdentifier::notPositive(self::class, $value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
