<?php

declare(strict_types=1);

namespace Infrastructure\Event;

use Domain\Shared\Uuid;

final readonly class EventId extends Uuid {}
