<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox; see docs/adr/0007-domain-events-and-transactional-outbox.md.
 *
 * Timestamps keep microseconds: with Laravel's default precision (0) PostgreSQL
 * rounds 12:00:00.6 up to 12:00:01, and a fresh event would not be due yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->bigInteger('position')->generatedAs()->always(); // insertion order, for delivery order
            $table->string('event_name', 64);
            $table->uuid('aggregate_id');
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('available_at', 6);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('published_at', 6)->nullable();
            $table->timestampTz('dead_at', 6)->nullable();

            $table->index('aggregate_id');
        });

        // The relay only ever scans pending rows; keep that index small.
        DB::statement('CREATE INDEX outbox_pending ON outbox (available_at, position) WHERE published_at IS NULL AND dead_at IS NULL');

        Schema::create('event_deliveries', function (Blueprint $table): void {
            $table->foreignUuid('event_id')->constrained('outbox')->cascadeOnDelete();
            $table->string('subscriber', 64);
            $table->timestampTz('delivered_at', 6);

            $table->primary(['event_id', 'subscriber']);
        });

        Schema::create('audit_log', function (Blueprint $table): void {
            $table->uuid('event_id')->primary(); // one row per event, so replays are no-ops
            $table->string('event_name', 64);
            $table->uuid('aggregate_id');
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('recorded_at', 6);

            $table->index(['aggregate_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('event_deliveries');
        Schema::dropIfExists('outbox');
    }
};
