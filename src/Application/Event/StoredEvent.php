<?php

declare(strict_types=1);

namespace Application\Event;

/**
 * A domain event as stored in the outbox: what subscribers receive.
 */
final readonly class StoredEvent
{
    /**
     * @param  array<string, scalar|null>  $payload
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $aggregateId,
        public array $payload,
        public \DateTimeImmutable $occurredAt,
        public int $attempts = 0,
    ) {}
}
