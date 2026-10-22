<?php

declare(strict_types=1);

use Infrastructure\Event\PostgresOutbox;

require_once __DIR__ . '/../../Contracts/OutboxContract.php';

outboxContract(fn (): PostgresOutbox => app(PostgresOutbox::class));
