<?php

declare(strict_types=1);

/*
 * Child process for ConcurrentBookingTest: boots the application, waits for a
 * shared start instant so all siblings fire together, then runs the real use case.
 * Prints exactly one word: booked | slot_unavailable | error:<message>
 *
 * Usage: php attempt-booking.php <startAtMicrotime> <spaceId> <customerId>
 */

use Application\Booking\BookSpace;
use Application\Booking\BookSpaceHandler;
use Domain\Booking\Exception\SlotUnavailable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

[, $startAt, $spaceId, $customerId] = $argv;

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$handler = $app->make(BookSpaceHandler::class);
$app->make('db')->connection()->getPdo(); // connect before the barrier

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $handler->handle(new BookSpace($spaceId, (int) $customerId, '2026-12-01T14:00:00Z', '2026-12-01T15:00:00Z'));
    echo 'booked';
} catch (SlotUnavailable) {
    echo 'slot_unavailable';
} catch (Throwable $e) {
    echo 'error:' . $e::class . ': ' . $e->getMessage();
}
