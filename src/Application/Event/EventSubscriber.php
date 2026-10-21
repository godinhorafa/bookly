<?php

declare(strict_types=1);

namespace Application\Event;

/**
 * Observer: reacts to stored domain events. Delivery is at least once, so
 * handle() must be safe to run again for the same event (e.g. key on its id).
 */
interface EventSubscriber
{
    /**
     * Stable identifier used to remember which subscribers already handled an event.
     */
    public function name(): string;

    public function isInterestedIn(string $eventName): bool;

    public function handle(StoredEvent $event): void;
}
