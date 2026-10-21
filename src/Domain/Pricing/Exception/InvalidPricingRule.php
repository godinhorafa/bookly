<?php

declare(strict_types=1);

namespace Domain\Pricing\Exception;

use Domain\Shared\Exception\DomainException;

final class InvalidPricingRule extends DomainException
{
    public static function percentOutOfRange(int $percent): self
    {
        return new self("Pricing percentage must be between 1 and 100, got {$percent}.");
    }

    public static function invalidTime(string $time): self
    {
        return new self("Expected a time as HH:MM between 00:00 and 24:00, got \"{$time}\".");
    }

    public static function emptyWindow(string $start, string $end): self
    {
        return new self("Peak window must end after it starts on the same day, got {$start}-{$end}.");
    }

    public static function invalidWeekdays(): self
    {
        return new self('Peak window needs at least one ISO weekday between 1 (Monday) and 7 (Sunday).');
    }

    public static function invalidMinimumDuration(int $minutes): self
    {
        return new self("Minimum duration must be positive, got {$minutes} minutes.");
    }
}
