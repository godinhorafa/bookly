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
        Schema::table('spaces', function (Blueprint $table): void {
            $table->string('cancellation_policy', 16)->default('moderate');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestampTz('cancelled_at')->nullable();
            $table->unsignedInteger('refund_amount')->nullable();
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->string('code', 32)->primary();
            $table->string('discount_type', 16);
            $table->unsignedInteger('value');
            $table->char('currency', 3)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE spaces
                ADD CONSTRAINT spaces_cancellation_policy_check
                    CHECK (cancellation_policy IN ('flexible', 'moderate', 'strict', 'non_refundable'))
            SQL);

        // A cancelled booking always records when and how much; an active one never does.
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
                ADD CONSTRAINT bookings_cancellation_consistency
                    CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL AND refund_amount IS NOT NULL)),
                ADD CONSTRAINT bookings_refund_not_above_price
                    CHECK (refund_amount IS NULL OR refund_amount <= total_amount)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE coupons
                ADD CONSTRAINT coupons_discount_type_check
                    CHECK (discount_type IN ('percentage', 'fixed')),
                ADD CONSTRAINT coupons_value_check
                    CHECK (value >= 1 AND (discount_type = 'fixed' OR value <= 100)),
                ADD CONSTRAINT coupons_currency_check
                    CHECK ((discount_type = 'fixed') = (currency IS NOT NULL))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_cancellation_consistency, DROP CONSTRAINT bookings_refund_not_above_price');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'refund_amount']);
        });

        Schema::table('spaces', function (Blueprint $table): void {
            $table->dropColumn('cancellation_policy');
        });
    }
};
