<?php

declare(strict_types=1);

namespace Application\Space;

use Domain\Shared\TimeSlot;
use Domain\Space\Space;

final readonly class Availability
{
    /**
     * @param  list<TimeSlot>  $busy  slots held by active bookings, in order
     */
    public function __construct(
        public Space $space,
        public TimeSlot $range,
        public array $busy,
    ) {}
}
