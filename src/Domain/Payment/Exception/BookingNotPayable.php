<?php

declare(strict_types=1);

namespace Domain\Payment\Exception;

use Domain\Booking\BookingId;
use Domain\Booking\BookingStatus;
use Domain\Shared\Exception\DomainException;

final class BookingNotPayable extends DomainException
{
    public static function because(BookingId $id, BookingStatus $status): self
    {
        return new self("Booking {$id} is {$status->value} and cannot be paid.");
    }

    public static function holdEnded(BookingId $id, \DateTimeImmutable $holdUntil): self
    {
        return new self("Booking {$id} had to be paid by {$holdUntil->format(DATE_ATOM)} and can no longer be paid. Book the slot again if it is still free.");
    }
}
