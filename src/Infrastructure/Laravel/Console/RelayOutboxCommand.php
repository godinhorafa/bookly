<?php

declare(strict_types=1);

namespace Infrastructure\Laravel\Console;

use Application\Event\OutboxRelay;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Deliver pending domain events to their subscribers')]
#[Signature('outbox:relay {--batch=50 : Events per batch} {--until-empty : Keep relaying until nothing is due}')]
final class RelayOutboxCommand extends Command
{
    public function handle(OutboxRelay $relay): int
    {
        $batch = max(1, (int) $this->option('batch'));

        do {
            $report = $relay->relay($batch);
            $this->info("published: {$report->published}, retrying: {$report->retried}, dead: {$report->dead}");
            // Later events of a booking only become due once earlier ones are out, so loop.
        } while ($this->option('until-empty') && $report->processed() > 0);

        return self::SUCCESS;
    }
}
