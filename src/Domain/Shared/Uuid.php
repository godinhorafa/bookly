<?php

declare(strict_types=1);

namespace Domain\Shared;

use Domain\Shared\Exception\InvalidIdentifier;

/**
 * Base for UUID-backed identifiers. Subclasses give each aggregate its own type,
 * so a SpaceId can never be passed where a BookingId is expected.
 */
abstract readonly class Uuid implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    final public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidIdentifier::notAUuid(static::class, $value);
        }
    }

    public static function fromString(string $value): static
    {
        return new static(strtolower($value));
    }

    public static function generate(): static
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return new static(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    public function equals(self $other): bool
    {
        return $other instanceof static && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
