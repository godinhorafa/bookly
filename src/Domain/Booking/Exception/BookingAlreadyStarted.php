<?php

declare(strict_types=1);

namespace Domain\Booking\Exception;

use Domain\Booking\BookingId;
use Domain\Shared\Exception\DomainException;

final class BookingAlreadyStarted extends DomainException
{
    public static function cannotCancel(BookingId $id): self
    {
        return new self("Booking {$id} has already started and can no longer be cancelled.");
    }
}
