<?php

declare(strict_types=1);

namespace Infrastructure\Event;

use Application\Event\Outbox;
use Application\Event\StoredEvent;
use Domain\Shared\Event\DomainEvent;
use Illuminate\Database\ConnectionInterface;
use Infrastructure\Persistence\Postgres\Timestamptz;

/**
 * Outbox on the application's own database connection, so appending events
 * joins whatever transaction the use case has open.
 */
final readonly class PostgresOutbox implements Outbox
{
    public function __construct(private ConnectionInterface $db) {}

    public function append(\DateTimeImmutable $occurredAt, DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->db->table('outbox')->insert([
                'id' => EventId::generate()->value,
                'event_name' => $event::name(),
                'aggregate_id' => $event->aggregateId(),
                'payload' => json_encode($event->payload(), JSON_THROW_ON_ERROR),
                'occurred_at' => Timestamptz::toDatabase($occurredAt),
                'available_at' => Timestamptz::toDatabase($occurredAt),
            ]);
        }
    }

    public function claimDue(\DateTimeImmutable $now, int $limit, int $leaseSeconds): array
    {
        // Pushing available_at forward is the lease: other relays skip these rows
        // until it expires, and SKIP LOCKED stops two relays racing for the same row.
        // Only the oldest pending event of each aggregate is due, so subscribers
        // see booking.requested before booking.confirmed even across retries.
        /** @var list<object{id: string, position: int, event_name: string, aggregate_id: string, payload: string, occurred_at: string, attempts: int}> $rows columns are NOT NULL in the schema */
        $rows = $this->db->select(<<<'SQL'
            UPDATE outbox SET available_at = ?
            WHERE id IN (
                SELECT id FROM outbox AS candidate
                WHERE published_at IS NULL AND dead_at IS NULL AND available_at <= ?
                  -- per-aggregate order: wait until earlier events of the same booking are out
                  AND NOT EXISTS (
                      SELECT 1 FROM outbox AS earlier
                      WHERE earlier.aggregate_id = candidate.aggregate_id
                        AND earlier.position < candidate.position
                        AND earlier.published_at IS NULL AND earlier.dead_at IS NULL
                  )
                ORDER BY position
                LIMIT ?
                FOR UPDATE SKIP LOCKED
            )
            RETURNING id, position, event_name, aggregate_id, payload, occurred_at, attempts
            SQL, [
            Timestamptz::toDatabase($now->modify("+{$leaseSeconds} seconds")),
            Timestamptz::toDatabase($now),
            $limit,
        ]);

        usort($rows, static fn (object $a, object $b): int => $a->position <=> $b->position);

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @param  object{id: string, event_name: string, aggregate_id: string, payload: string, occurred_at: string, attempts: int}  $row
     */
    private function hydrate(object $row): StoredEvent
    {
        /** @var array<string, scalar|null> $payload written by append() from DomainEvent::payload() */
        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);

        return new StoredEvent($row->id, $row->event_name, $row->aggregate_id, $payload, new \DateTimeImmutable($row->occurred_at), $row->attempts);
    }

    public function deliveredTo(string $eventId): array
    {
        /** @var list<string> */
        return $this->db->table('event_deliveries')->where('event_id', $eventId)->pluck('subscriber')->all();
    }

    public function markDelivered(string $eventId, string $subscriber, \DateTimeImmutable $at): void
    {
        $this->db->table('event_deliveries')->insertOrIgnore([
            'event_id' => $eventId,
            'subscriber' => $subscriber,
            'delivered_at' => Timestamptz::toDatabase($at),
        ]);
    }

    public function markPublished(string $eventId, \DateTimeImmutable $at): void
    {
        $this->db->table('outbox')->where('id', $eventId)->update(['published_at' => Timestamptz::toDatabase($at)]);
    }

    public function scheduleRetry(string $eventId, string $error, \DateTimeImmutable $retryAt): void
    {
        $this->db->table('outbox')->where('id', $eventId)->update([
            'attempts' => $this->db->raw('attempts + 1'),
            'last_error' => $error,
            'available_at' => Timestamptz::toDatabase($retryAt),
        ]);
    }

    public function markDead(string $eventId, string $error, \DateTimeImmutable $at): void
    {
        $this->db->table('outbox')->where('id', $eventId)->update([
            'attempts' => $this->db->raw('attempts + 1'),
            'last_error' => $error,
            'dead_at' => Timestamptz::toDatabase($at),
        ]);
    }
}
