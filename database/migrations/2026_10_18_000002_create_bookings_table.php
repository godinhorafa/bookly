<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Overbooking is prevented by the database, not by application code.
 * See docs/adr/0002-exclusion-constraint-for-overbooking.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('space_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('total_amount');
            $table->char('currency', 3);
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE bookings
                ADD COLUMN period tstzrange NOT NULL,
                ADD CONSTRAINT bookings_status_check
                    CHECK (status IN ('pending', 'confirmed', 'cancelled')),
                ADD CONSTRAINT bookings_period_half_open
                    CHECK (NOT isempty(period) AND lower_inc(period) AND NOT upper_inc(period)),
                ADD CONSTRAINT bookings_no_overlap
                    EXCLUDE USING gist (space_id WITH =, period WITH &&)
                    WHERE (status <> 'cancelled')
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
