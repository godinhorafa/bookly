<?php

declare(strict_types=1);

namespace Infrastructure\Laravel\Console;

use Application\Event\OutboxRelay;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Deliver pending domain events to their subscribers')]
#[Signature('outbox:relay
    {--batch=50 : Events per batch}
    {--until-empty : Keep relaying until nothing is due}
    {--watch : Keep running, polling for new events until stopped (SIGINT/SIGTERM)}
    {--sleep=250 : With --watch, milliseconds to wait when nothing is due}')]
final class RelayOutboxCommand extends Command
{
    private bool $stopping = false;

    public function handle(OutboxRelay $relay): int
    {
        $batch = max(1, (int) $this->option('batch'));
        $watch = (bool) $this->option('watch');
        $sleepMicroseconds = max(10, (int) $this->option('sleep')) * 1_000;

        if ($watch) {
            // Finish the batch in hand, then exit: an event is never left half-delivered.
            $this->trap([SIGINT, SIGTERM], function (): void {
                $this->stopping = true;
            });
        }

        do {
            $report = $relay->relay($batch);

            if ($report->processed() > 0) {
                $this->info("published: {$report->published}, retrying: {$report->retried}, dead: {$report->dead}");
            } elseif ($watch && ! $this->stopping) {
                usleep($sleepMicroseconds);
            }
            // Later events of a booking only become due once earlier ones are out, so loop.
        } while (! $this->stopping && ($watch || ($this->option('until-empty') && $report->processed() > 0)));

        return self::SUCCESS;
    }
}
