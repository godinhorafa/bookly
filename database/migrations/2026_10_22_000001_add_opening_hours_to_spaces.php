<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table): void {
            // List of {weekdays, start, end} windows in the space's local time; see OpeningHoursJson
            $table->jsonb('opening_hours')->default('[{"weekdays":[1,2,3,4,5,6,7],"start":"00:00","end":"24:00"}]');
        });
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table): void {
            $table->dropColumn('opening_hours');
        });
    }
};
