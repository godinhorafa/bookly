<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Space\SpaceRepository;
use Illuminate\Support\Facades\DB;
use Tests\Support\Builders;

/*
 * Data must be committed so separate processes can see it, which is why this
 * suite uses DatabaseTruncation (see tests/Pest.php) instead of transactions.
 */

const CONTENDERS = 8;

afterEach(function (): void {
    DB::statement('TRUNCATE bookings, spaces, users RESTART IDENTITY CASCADE');
});

it('lets exactly one of many simultaneous requests book the same slot', function (): void {
    $space = Builders::space();
    app(SpaceRepository::class)->save($space);
    $customerId = User::factory()->create()->id;

    $startAt = microtime(true) + 2.0;
    // Relative to today so the booking rules (notice, horizon) keep accepting it.
    $slotStart = (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->setTime(14, 0);
    $slot = [$slotStart->format(DATE_ATOM), $slotStart->modify('+1 hour')->format(DATE_ATOM)];
    $env = array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) config('database.connections.pgsql.host'),
        'DB_PORT' => (string) config('database.connections.pgsql.port'),
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
        'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
        'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
    ]);

    $processes = [];
    for ($i = 0; $i < CONTENDERS; $i++) {
        $command = [PHP_BINARY, __DIR__ . '/attempt-booking.php', (string) $startAt, $space->id->value, (string) $customerId, ...$slot];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        expect($process)->toBeResource();
        $processes[] = [$process, $pipes];
    }

    $outcomes = [];
    foreach ($processes as [$process, $pipes]) {
        $outcomes[] = trim((string) stream_get_contents($pipes[1])) ?: trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    $counts = array_count_values($outcomes);
    ksort($counts);

    // On failure Pest prints $counts, which includes any "error:..." outcome verbatim.
    expect($counts)->toBe(['booked' => 1, 'slot_unavailable' => CONTENDERS - 1])
        ->and(DB::table('bookings')->count())->toBe(1);
});
