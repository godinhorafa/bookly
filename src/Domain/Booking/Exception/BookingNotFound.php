<?php

declare(strict_types=1);

namespace Domain\Booking\Exception;

use Domain\Booking\BookingId;
use Domain\Shared\Exception\DomainException;

final class BookingNotFound extends DomainException
{
    public static function withId(BookingId $id): self
    {
        return new self("Booking {$id} was not found.");
    }
}
