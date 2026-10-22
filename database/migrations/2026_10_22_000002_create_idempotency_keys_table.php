<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            $table->char('fingerprint', 64); // sha256 of method, path and body
            $table->unsignedSmallInteger('response_status')->nullable(); // null while in flight
            $table->text('response_body')->nullable();
            $table->string('response_content_type')->nullable();
            $table->timestampTz('created_at', 6);

            $table->primary(['user_id', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
