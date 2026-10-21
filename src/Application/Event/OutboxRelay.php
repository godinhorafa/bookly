<?php

declare(strict_types=1);

namespace Application\Event;

use Domain\Shared\Clock;

/**
 * Delivers outbox events to every interested subscriber.
 *
 * Delivery is tracked per (event, subscriber): if the webhook fails but the
 * e-mail went out, the retry only calls the webhook again. Failed events back
 * off exponentially and are parked as dead after MAX_ATTEMPTS.
 */
final readonly class OutboxRelay
{
    public const int MAX_ATTEMPTS = 8;

    private const int LEASE_SECONDS = 120;

    /** @var list<EventSubscriber> */
    private array $subscribers;

    public function __construct(
        private Outbox $outbox,
        private Clock $clock,
        EventSubscriber ...$subscribers,
    ) {
        $this->subscribers = array_values($subscribers);
    }

    public function relay(int $batchSize = 50): RelayReport
    {
        $published = $retried = $dead = 0;

        foreach ($this->outbox->claimDue($this->clock->now(), $batchSize, self::LEASE_SECONDS) as $event) {
            $errors = $this->deliver($event);
            $now = $this->clock->now();

            if ($errors === []) {
                $this->outbox->markPublished($event->id, $now);
                $published++;
            } elseif ($event->attempts + 1 >= self::MAX_ATTEMPTS) {
                $this->outbox->markDead($event->id, implode("\n", $errors), $now);
                $dead++;
            } else {
                $this->outbox->scheduleRetry($event->id, implode("\n", $errors), $now->modify('+' . self::backoffSeconds($event->attempts + 1) . ' seconds'));
                $retried++;
            }
        }

        return new RelayReport($published, $retried, $dead);
    }

    /**
     * 30s, 1m, 2m, 4m... capped at one hour.
     */
    public static function backoffSeconds(int $attempt): int
    {
        return min(3600, 30 * 2 ** ($attempt - 1));
    }

    /**
     * @return list<string> one message per subscriber that failed
     */
    private function deliver(StoredEvent $event): array
    {
        $done = $this->outbox->deliveredTo($event->id);
        $errors = [];

        foreach ($this->subscribers as $subscriber) {
            if (! $subscriber->isInterestedIn($event->name) || in_array($subscriber->name(), $done, true)) {
                continue;
            }

            try {
                $subscriber->handle($event);
                $this->outbox->markDelivered($event->id, $subscriber->name(), $this->clock->now());
            } catch (\Throwable $e) {
                $errors[] = "{$subscriber->name()}: {$e->getMessage()}";
            }
        }

        return $errors;
    }
}
