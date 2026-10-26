<?php

declare(strict_types=1);

use Application\Event\Outbox;
use Domain\Booking\CustomerId;
use Domain\Booking\Event\BookingRequested;
use Domain\Space\SpaceId;
use Illuminate\Support\Facades\DB;
use Tests\Support\Builders;

/*
 * `outbox:relay --watch` is what makes notifications feel real-time: it runs as
 * a separate long-lived process, so it is tested as one.
 */

afterEach(function (): void {
    DB::statement('TRUNCATE outbox, event_deliveries, audit_log RESTART IDENTITY CASCADE');
});

it('delivers events appended while it runs and stops cleanly on SIGTERM', function (): void {
    $env = array_merge(getenv(), [
        // Laravel ignores signal traps under APP_ENV=testing; this test is about them.
        'APP_ENV' => 'local',
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
        'WEBHOOK_ENDPOINTS' => '',
    ]);
    $relay = proc_open(
        [PHP_BINARY, 'artisan', 'outbox:relay', '--watch', '--sleep=50'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
        $env,
    );
    expect($relay)->toBeResource();

    $event = BookingRequested::of(Builders::booking(SpaceId::generate(), new CustomerId(1)));
    app(Outbox::class)->append(new DateTimeImmutable, $event);

    $deadline = microtime(true) + 15;
    while (DB::table('outbox')->whereNotNull('published_at')->doesntExist() && microtime(true) < $deadline) {
        usleep(50_000);
    }

    proc_terminate($relay, SIGTERM);
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($relay);

    expect(DB::table('outbox')->whereNotNull('published_at')->count())->toBe(1, $errors)
        ->and(DB::table('audit_log')->pluck('event_name')->all())->toBe(['booking.requested'])
        ->and($output)->toContain('published: 1')
        ->and($exitCode)->toBe(0, $errors);
});
