<?php

declare(strict_types=1);

namespace Infrastructure\Laravel\Console;

use Application\Booking\ExpireUnpaidBookingsHandler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Expire bookings that were not paid in time, giving their slots back')]
#[Signature('bookings:expire {--limit=100 : Bookings per run}')]
final class ExpireUnpaidBookingsCommand extends Command
{
    public function handle(ExpireUnpaidBookingsHandler $handler): int
    {
        $expired = $handler->handle(max(1, (int) $this->option('limit')));
        $this->info("expired: {$expired}");

        return self::SUCCESS;
    }
}
