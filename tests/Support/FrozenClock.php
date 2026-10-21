<?php

declare(strict_types=1);

namespace Tests\Support;

use Domain\Shared\Clock;

final class FrozenClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $now = '2026-11-01T12:00:00Z')
    {
        $this->now = new \DateTimeImmutable($now);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function travelTo(string $now): void
    {
        $this->now = new \DateTimeImmutable($now);
    }
}
