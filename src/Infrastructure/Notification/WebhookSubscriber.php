<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use Application\Event\EventSubscriber;
use Application\Event\StoredEvent;
use Domain\Shared\Clock;
use Illuminate\Http\Client\Factory as Http;

/**
 * POSTs booking events to one external endpoint, signed with HMAC.
 * One instance per endpoint, so each endpoint's delivery is tracked (and retried) separately.
 */
final readonly class WebhookSubscriber implements EventSubscriber
{
    public function __construct(
        private Http $http,
        private Clock $clock,
        private string $url,
        private string $secret,
    ) {}

    public function name(): string
    {
        return 'webhook:' . substr(hash('sha256', $this->url), 0, 12);
    }

    public function isInterestedIn(string $eventName): bool
    {
        return str_starts_with($eventName, 'booking.');
    }

    public function handle(StoredEvent $event): void
    {
        $body = json_encode([
            'id' => $event->id,
            'type' => $event->name,
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            'data' => $event->payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->http
            ->withHeaders([
                'Bookly-Event-Id' => $event->id, // lets receivers deduplicate redeliveries
                'Bookly-Signature' => WebhookSignature::sign($body, $this->secret, $this->clock->now()->getTimestamp()),
            ])
            ->withBody($body, 'application/json')
            ->timeout(10)
            ->post($this->url)
            ->throw();
    }
}
