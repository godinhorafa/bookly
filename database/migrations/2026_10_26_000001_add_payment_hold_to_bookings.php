<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unpaid bookings hold their slot only until hold_until, then expire and give it
 * back. See docs/adr/0010-unpaid-bookings-expire.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
                ADD COLUMN hold_until timestamptz(6),
                DROP CONSTRAINT bookings_status_check,
                ADD CONSTRAINT bookings_status_check
                    CHECK (status IN ('pending', 'confirmed', 'cancelled', 'expired')),
                DROP CONSTRAINT bookings_no_overlap,
                ADD CONSTRAINT bookings_no_overlap
                    EXCLUDE USING gist (space_id WITH =, period WITH &&)
                    WHERE (status IN ('pending', 'confirmed'))
            SQL);

        // Existing bookings get the default 15-minute hold from when they were made.
        DB::statement("UPDATE bookings SET hold_until = created_at + interval '15 minutes'");
        DB::statement('ALTER TABLE bookings ALTER COLUMN hold_until SET NOT NULL');

        // The expiry job only ever looks at pending bookings, by deadline.
        DB::statement("CREATE INDEX bookings_pending_hold_until ON bookings (hold_until) WHERE status = 'pending'");
    }

    public function down(): void
    {
        // Expired bookings become unpaid cancellations, which the old schema can hold.
        DB::statement("UPDATE bookings SET status = 'cancelled', cancelled_at = hold_until, refund_amount = 0 WHERE status = 'expired'");
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
                DROP COLUMN hold_until,
                DROP CONSTRAINT bookings_status_check,
                ADD CONSTRAINT bookings_status_check
                    CHECK (status IN ('pending', 'confirmed', 'cancelled')),
                DROP CONSTRAINT bookings_no_overlap,
                ADD CONSTRAINT bookings_no_overlap
                    EXCLUDE USING gist (space_id WITH =, period WITH &&)
                    WHERE (status <> 'cancelled')
            SQL);
    }
};
