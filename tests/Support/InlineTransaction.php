<?php

declare(strict_types=1);

namespace Tests\Support;

use Application\Transaction;

/**
 * Runs the work directly. Can be told to fail once, to simulate the database
 * going away right after an external call succeeded.
 */
final class InlineTransaction implements Transaction
{
    private int $failuresLeft = 0;

    public function failNext(): void
    {
        $this->failuresLeft = 1;
    }

    public function run(\Closure $work): mixed
    {
        if ($this->failuresLeft > 0) {
            $this->failuresLeft--;

            throw new \RuntimeException('Simulated database failure');
        }

        return $work();
    }
}
