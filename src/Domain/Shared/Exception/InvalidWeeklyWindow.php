<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

final class InvalidWeeklyWindow extends DomainException
{
    public static function invalidTime(string $time): self
    {
        return new self("Expected a time as HH:MM between 00:00 and 24:00, got \"{$time}\".");
    }

    public static function emptyWindow(string $start, string $end): self
    {
        return new self("A weekly window must end after it starts on the same day, got {$start}-{$end}.");
    }

    public static function invalidWeekdays(): self
    {
        return new self('A weekly window needs at least one ISO weekday between 1 (Monday) and 7 (Sunday).');
    }
}
