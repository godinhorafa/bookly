<?php

declare(strict_types=1);

use Tests\Support\InMemory\InMemoryOutbox;

require_once __DIR__ . '/../../Contracts/OutboxContract.php';

outboxContract(fn (): InMemoryOutbox => new InMemoryOutbox);
