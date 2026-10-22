<?php

declare(strict_types=1);

use App\Http\Middleware\Idempotency;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('outbox:relay --until-empty')->everyMinute()->withoutOverlapping();

Schedule::call(fn () => DB::table('idempotency_keys')->where('created_at', '<', now()->subHours(Idempotency::RETENTION_HOURS))->delete())
    ->name('prune-idempotency-keys')
    ->hourly();
