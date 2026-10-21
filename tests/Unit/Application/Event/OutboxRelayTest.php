<?php

declare(strict_types=1);

use Application\Event\EventSubscriber;
use Application\Event\OutboxRelay;
use Application\Event\StoredEvent;
use Domain\Booking\CustomerId;
use Domain\Booking\Event\BookingConfirmed;
use Domain\Booking\Event\BookingRequested;
use Domain\Space\SpaceId;
use Tests\Support\Builders;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\InMemoryOutbox;

/**
 * A subscriber that records what it received and can be told to fail.
 */
function recordingSubscriber(string $name, ?string $onlyEvent = null): EventSubscriber
{
    return new class($name, $onlyEvent) implements EventSubscriber
    {
        /** @var list<string> */
        public array $received = [];

        public int $failuresLeft = 0;

        public function __construct(private readonly string $subscriberName, private readonly ?string $onlyEvent) {}

        public function name(): string
        {
            return $this->subscriberName;
        }

        public function isInterestedIn(string $eventName): bool
        {
            return $this->onlyEvent === null || $this->onlyEvent === $eventName;
        }

        public function handle(StoredEvent $event): void
        {
            if ($this->failuresLeft > 0) {
                $this->failuresLeft--;

                throw new RuntimeException('endpoint returned 503');
            }

            $this->received[] = $event->name;
        }
    };
}

beforeEach(function (): void {
    $this->clock = new FrozenClock('2026-11-01T12:00:00Z');
    $this->outbox = new InMemoryOutbox;
    $this->audit = recordingSubscriber('audit');
    $this->email = recordingSubscriber('email', 'booking.confirmed');
    $this->webhook = recordingSubscriber('webhook');
    $this->relay = new OutboxRelay($this->outbox, $this->clock, $this->audit, $this->email, $this->webhook);

    $booking = Builders::booking(SpaceId::generate(), new CustomerId(1));
    $booking->confirm();
    $this->outbox->append($this->clock->now(), BookingRequested::of($booking), BookingConfirmed::of($booking));
});

it('notifies every interested subscriber, one event per booking at a time', function (): void {
    expect($this->relay->relay()->published)->toBe(1) // booking.confirmed waits for booking.requested
        ->and($this->relay->relay()->published)->toBe(1)
        ->and($this->relay->relay()->processed())->toBe(0)
        ->and($this->audit->received)->toBe(['booking.requested', 'booking.confirmed'])
        ->and($this->email->received)->toBe(['booking.confirmed'])
        ->and($this->webhook->received)->toBe(['booking.requested', 'booking.confirmed']);
});

it('retries only the subscriber that failed, and keeps the order', function (): void {
    $this->webhook->failuresLeft = 1;

    expect($this->relay->relay()->retried)->toBe(1)
        ->and($this->relay->relay()->processed())->toBe(0); // confirmed can't overtake the failed requested

    $this->clock->travelTo('2026-11-01T12:00:30Z'); // first backoff is 30s
    $this->relay->relay();
    $this->relay->relay();

    expect($this->audit->received)->toBe(['booking.requested', 'booking.confirmed']) // audit not repeated
        ->and($this->email->received)->toBe(['booking.confirmed'])
        ->and($this->webhook->received)->toBe(['booking.requested', 'booking.confirmed']);
});

it('waits for the backoff before retrying', function (): void {
    $this->webhook->failuresLeft = 99;
    $this->relay->relay();

    $this->clock->travelTo('2026-11-01T12:00:29Z');

    expect($this->relay->relay()->processed())->toBe(0);
});

it('parks an event as dead after the maximum number of attempts, then moves on', function (): void {
    $this->webhook->failuresLeft = OutboxRelay::MAX_ATTEMPTS;
    $dead = 0;

    for ($attempt = 1; $attempt <= OutboxRelay::MAX_ATTEMPTS; $attempt++) {
        $dead += $this->relay->relay()->dead;
        $this->clock->travelTo($this->clock->now()->modify('+2 hours')->format(DATE_ATOM)); // past any backoff
    }

    // The dead event no longer blocks the next one, and the webhook is healthy again.
    expect($dead)->toBe(1)
        ->and($this->relay->relay()->published)->toBe(1)
        ->and($this->webhook->received)->toBe(['booking.confirmed']);
});

it('backs off exponentially, capped at one hour', function (): void {
    expect(array_map(OutboxRelay::backoffSeconds(...), [1, 2, 3, 4, 8, 20]))->toBe([30, 60, 120, 240, 3600, 3600]);
});
