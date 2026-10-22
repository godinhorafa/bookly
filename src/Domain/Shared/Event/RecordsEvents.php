<?php

declare(strict_types=1);

namespace Domain\Shared\Event;

/**
 * Lets an aggregate collect events as its state changes. The application layer
 * releases them and stores them in the same transaction as the aggregate.
 */
trait RecordsEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    protected function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
