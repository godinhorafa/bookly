<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->restrictOnDelete();
            // 1, 2, 3... per booking; part of the gateway idempotency key
            $table->unsignedSmallInteger('attempt');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->char('currency', 3);
            $table->string('gateway', 32);
            $table->string('reference')->nullable();
            $table->string('refund_reference')->nullable();
            $table->string('status', 24);
            $table->string('decline_reason')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'attempt']);
            $table->unique(['gateway', 'reference']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments
                ADD CONSTRAINT payments_status_check
                    CHECK (status IN ('succeeded', 'declined', 'refunded', 'partially_refunded')),
                ADD CONSTRAINT payments_refund_within_amount
                    CHECK (refunded_amount <= amount),
                ADD CONSTRAINT payments_declined_has_no_refund
                    CHECK (status <> 'declined' OR refunded_amount = 0)
            SQL);

        // At most one successful charge per booking: a second one would be a double charge.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX payments_one_settled_charge_per_booking
                ON payments (booking_id) WHERE status <> 'declined'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
