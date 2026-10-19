<?php

declare(strict_types=1);

namespace Domain\Booking\Exception;

use Domain\Booking\TimeSlot;
use Domain\Shared\Exception\DomainException;
use Domain\Space\SpaceId;

final class SlotUnavailable extends DomainException
{
    public static function for(SpaceId $spaceId, TimeSlot $slot): self
    {
        return new self(sprintf(
            'Space %s is already booked between %s and %s.',
            $spaceId,
            $slot->start->format(DATE_ATOM),
            $slot->end->format(DATE_ATOM),
        ));
    }
}
