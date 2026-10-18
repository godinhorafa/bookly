<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Unit tests deliberately do NOT extend the Laravel TestCase: the domain
 * is plain PHP and its tests run without booting the framework.
 */

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Integration', 'Feature');
