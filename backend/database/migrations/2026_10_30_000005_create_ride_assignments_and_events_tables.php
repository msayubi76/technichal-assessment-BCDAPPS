<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who got the ride and where they were when they accepted it.
        // The unique ride_id means the database itself refuses a second
        // driver for the same ride, even if two requests slip through together.
        Schema::create('ride_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ride_id')->unique()->constrained('rides');
            $table->foreignId('driver_id')->constrained('drivers');
            $table->decimal('driver_lat', 10, 7)->nullable();
            $table->decimal('driver_lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_to_pickup_meters')->nullable();
            $table->unsignedInteger('pickup_eta_seconds')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['driver_id', 'accepted_at']);
        });

        // A history of every status change. Handy for support questions
        // ("who cancelled and when?") and for debugging.
        Schema::create('ride_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ride_id')->constrained('rides');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');

            $table->index(['ride_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_events');
        Schema::dropIfExists('ride_assignments');
    }
};
