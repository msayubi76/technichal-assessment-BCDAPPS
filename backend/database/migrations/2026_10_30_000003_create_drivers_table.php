<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained();
            $table->string('category', 20);
            $table->string('vehicle_model');
            $table->string('vehicle_color', 30);
            $table->string('plate_number', 20)->unique();

            // offline, available or on_trip
            $table->string('status', 20)->default('offline');

            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->timestamps();

            // Matching always asks "available drivers of this category in this
            // city", so the index follows that order.
            $table->index(['city_id', 'status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
