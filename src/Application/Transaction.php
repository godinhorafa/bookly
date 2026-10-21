<?php

declare(strict_types=1);

namespace Application;

/**
 * Port for "all of this or none of it". Infrastructure implements it with a
 * database transaction; unit tests run the callback directly.
 */
interface Transaction
{
    /**
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    public function run(\Closure $work): mixed;
}
