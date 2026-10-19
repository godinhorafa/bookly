<?php

declare(strict_types=1);

namespace Domain\Booking\Exception;

use Domain\Booking\BookingId;
use Domain\Booking\BookingStatus;
use Domain\Shared\Exception\DomainException;

final class InvalidBookingTransition extends DomainException
{
    public static function between(BookingId $id, BookingStatus $from, BookingStatus $to): self
    {
        return new self("Booking {$id} cannot go from {$from->value} to {$to->value}.");
    }
}
