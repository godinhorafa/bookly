<?php

declare(strict_types=1);

namespace Domain\Booking\Exception;

use Domain\Shared\Exception\DomainException;

final class InvalidTimeSlot extends DomainException
{
    public static function endNotAfterStart(\DateTimeImmutable $start, \DateTimeImmutable $end): self
    {
        return new self(sprintf(
            'Time slot end (%s) must be after its start (%s).',
            $end->format(DATE_ATOM),
            $start->format(DATE_ATOM),
        ));
    }

    public static function unparseable(string $value): self
    {
        return new self("\"{$value}\" is not a valid date and time.");
    }
}
