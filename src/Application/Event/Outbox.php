<?php

declare(strict_types=1);

namespace Application\Event;

use Domain\Shared\Event\DomainEvent;

/**
 * Transactional outbox. Use cases append events in the same database transaction
 * as the aggregate change, so an event exists if and only if the change was
 * committed. A relay later delivers them to subscribers, at least once.
 */
interface Outbox
{
    public function append(\DateTimeImmutable $occurredAt, DomainEvent ...$events): void;

    /**
     * Leases up to $limit events that are due, so concurrent relays never take the same one.
     * An event is only due once every earlier event of the same aggregate has been
     * published (or parked as dead), which keeps delivery in order per aggregate.
     *
     * @return list<StoredEvent>
     */
    public function claimDue(\DateTimeImmutable $now, int $limit, int $leaseSeconds): array;

    /**
     * @return list<string> names of the subscribers that already handled the event
     */
    public function deliveredTo(string $eventId): array;

    public function markDelivered(string $eventId, string $subscriber, \DateTimeImmutable $at): void;

    public function markPublished(string $eventId, \DateTimeImmutable $at): void;

    public function scheduleRetry(string $eventId, string $error, \DateTimeImmutable $retryAt): void;

    public function markDead(string $eventId, string $error, \DateTimeImmutable $at): void;
}
