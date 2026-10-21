<?php

declare(strict_types=1);

namespace Domain\Space\Exception;

use Domain\Shared\Exception\DomainException;

final class InvalidSpace extends DomainException
{
    public static function blankName(): self
    {
        return new self('Space name cannot be blank.');
    }

    public static function invalidCapacity(int $capacity): self
    {
        return new self("Space capacity must be at least 1, got {$capacity}.");
    }

    public static function unknownTimezone(string $timezone): self
    {
        return new self("Unknown time zone \"{$timezone}\".");
    }

    public static function overlappingOpeningHours(): self
    {
        return new self('Opening hours windows must not overlap.');
    }
}
