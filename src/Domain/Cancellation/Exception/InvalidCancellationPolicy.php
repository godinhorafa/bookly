<?php

declare(strict_types=1);

namespace Domain\Cancellation\Exception;

use Domain\Shared\Exception\DomainException;

final class InvalidCancellationPolicy extends DomainException
{
    public static function negativeHours(int $hours): self
    {
        return new self("Refund tier hours cannot be negative, got {$hours}.");
    }

    public static function percentOutOfRange(int $percent): self
    {
        return new self("Refund percentage must be between 0 and 100, got {$percent}.");
    }

    public static function duplicateTier(int $hours): self
    {
        return new self("More than one refund tier starts at {$hours}h before.");
    }
}
