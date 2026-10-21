<?php

declare(strict_types=1);

namespace Infrastructure\Persistence;

use Application\Transaction;
use Illuminate\Database\ConnectionInterface;

final readonly class LaravelTransaction implements Transaction
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(\Closure $work): mixed
    {
        return $this->connection->transaction($work);
    }
}
