<?php

declare(strict_types=1);

namespace Application\Event;

final readonly class RelayReport
{
    public function __construct(
        public int $published = 0,
        public int $retried = 0,
        public int $dead = 0,
    ) {}

    public function processed(): int
    {
        return $this->published + $this->retried + $this->dead;
    }
}
