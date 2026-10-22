<?php

declare(strict_types=1);

namespace Domain\Shared\Event;

/**
 * Something that happened in the domain that other parts of the system may care about.
 * Events are plain data: a stable name, the aggregate they concern and a scalar payload,
 * so they can be stored in the outbox and sent to other services as JSON.
 */
interface DomainEvent
{
    /**
     * Stable, dotted name used on the wire, e.g. "booking.confirmed". Never rename it.
     */
    public static function name(): string;

    public function aggregateId(): string;

    /**
     * @return array<string, scalar|null>
     */
    public function payload(): array;
}
