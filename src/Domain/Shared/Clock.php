<?php

declare(strict_types=1);

namespace Domain\Shared;

/**
 * Time is an input like any other: injecting it keeps rules such as
 * "full refund up to 48h before" deterministic under test.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
