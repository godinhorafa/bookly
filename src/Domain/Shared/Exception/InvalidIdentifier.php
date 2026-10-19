<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

final class InvalidIdentifier extends DomainException
{
    public static function notAUuid(string $type, string $value): self
    {
        return new self(self::shortName($type) . " must be a UUID, got \"{$value}\".");
    }

    public static function notPositive(string $type, int $value): self
    {
        return new self(self::shortName($type) . " must be a positive integer, got {$value}.");
    }

    private static function shortName(string $type): string
    {
        return basename(str_replace('\\', '/', $type));
    }
}
