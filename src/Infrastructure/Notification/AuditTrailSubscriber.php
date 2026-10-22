<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use Application\Event\EventSubscriber;
use Application\Event\StoredEvent;
use Domain\Shared\Clock;
use Illuminate\Database\ConnectionInterface;
use Infrastructure\Persistence\Postgres\Timestamptz;

/**
 * Keeps an append-only history of everything that happened to each booking.
 */
final readonly class AuditTrailSubscriber implements EventSubscriber
{
    public function __construct(private ConnectionInterface $db, private Clock $clock) {}

    public function name(): string
    {
        return 'audit-trail';
    }

    public function isInterestedIn(string $eventName): bool
    {
        return true;
    }

    public function handle(StoredEvent $event): void
    {
        // Keyed on the event id, so a redelivery is a no-op.
        $this->db->table('audit_log')->insertOrIgnore([
            'event_id' => $event->id,
            'event_name' => $event->name,
            'aggregate_id' => $event->aggregateId,
            'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR),
            'occurred_at' => Timestamptz::toDatabase($event->occurredAt),
            'recorded_at' => Timestamptz::toDatabase($this->clock->now()),
        ]);
    }
}
