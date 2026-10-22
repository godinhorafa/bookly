<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use Application\Event\Outbox;
use Application\Event\StoredEvent;
use Domain\Shared\Event\DomainEvent;

/**
 * Same contract as PostgresOutbox (see tests/Contracts/OutboxContract.php).
 */
final class InMemoryOutbox implements Outbox
{
    /** @var array<string, array{event: StoredEvent, available_at: \DateTimeImmutable, published_at: ?\DateTimeImmutable, dead_at: ?\DateTimeImmutable, last_error: ?string}> */
    private array $rows = [];

    /** @var array<string, list<string>> */
    private array $deliveries = [];

    private int $sequence = 0;

    public function append(\DateTimeImmutable $occurredAt, DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $id = sprintf('00000000-0000-4000-8000-%012d', ++$this->sequence);
            $this->rows[$id] = [
                'event' => new StoredEvent($id, $event::name(), $event->aggregateId(), $event->payload(), $occurredAt),
                'available_at' => $occurredAt,
                'published_at' => null,
                'dead_at' => null,
                'last_error' => null,
            ];
        }
    }

    public function claimDue(\DateTimeImmutable $now, int $limit, int $leaseSeconds): array
    {
        $claimed = [];
        foreach ($this->rows as $id => $row) {
            if (count($claimed) === $limit) {
                break;
            }

            if ($this->isPending($row) && $row['available_at'] <= $now && ! $this->hasEarlierPending($id, $row['event']->aggregateId)) {
                $this->rows[$id]['available_at'] = $now->modify("+{$leaseSeconds} seconds");
                $claimed[] = $row['event'];
            }
        }

        return $claimed;
    }

    public function deliveredTo(string $eventId): array
    {
        return $this->deliveries[$eventId] ?? [];
    }

    public function markDelivered(string $eventId, string $subscriber, \DateTimeImmutable $at): void
    {
        $this->deliveries[$eventId] = array_values(array_unique([...$this->deliveredTo($eventId), $subscriber]));
    }

    public function markPublished(string $eventId, \DateTimeImmutable $at): void
    {
        $this->rows[$eventId]['published_at'] = $at;
    }

    public function scheduleRetry(string $eventId, string $error, \DateTimeImmutable $retryAt): void
    {
        $this->bump($eventId, $error);
        $this->rows[$eventId]['available_at'] = $retryAt;
    }

    public function markDead(string $eventId, string $error, \DateTimeImmutable $at): void
    {
        $this->bump($eventId, $error);
        $this->rows[$eventId]['dead_at'] = $at;
    }

    /**
     * @return list<string> names of every event appended so far, in order
     */
    public function names(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['event']->name, $this->rows));
    }

    /**
     * @param  array{published_at: ?\DateTimeImmutable, dead_at: ?\DateTimeImmutable}  $row
     */
    private function isPending(array $row): bool
    {
        return $row['published_at'] === null && $row['dead_at'] === null;
    }

    private function hasEarlierPending(string $id, string $aggregateId): bool
    {
        foreach ($this->rows as $otherId => $other) {
            if ($otherId === $id) {
                return false; // rows are kept in insertion order
            }

            if ($other['event']->aggregateId === $aggregateId && $this->isPending($other)) {
                return true;
            }
        }

        return false;
    }

    private function bump(string $eventId, string $error): void
    {
        $event = $this->rows[$eventId]['event'];
        $this->rows[$eventId]['event'] = new StoredEvent($event->id, $event->name, $event->aggregateId, $event->payload, $event->occurredAt, $event->attempts + 1);
        $this->rows[$eventId]['last_error'] = $error;
    }
}
