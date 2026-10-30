<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remembers the result of write requests that came with an
        // Idempotency-Key header. If the app retries the same request
        // (bad network, double tap), we send back the saved result
        // instead of doing the work twice.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('method', 10);
            $table->string('path');
            $table->char('request_hash', 64);

            // Empty while the first request is still running.
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();

            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
