<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Postgres;

/**
 * Explicit timestamptz mapping. Eloquent's datetime cast drops the UTC offset,
 * which silently shifts any value that is not already in the connection's zone.
 */
final class Timestamptz
{
    public static function toDatabase(?\DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function fromDatabase(?string $value): ?\DateTimeImmutable
    {
        return $value === null ? null : new \DateTimeImmutable($value);
    }
}
