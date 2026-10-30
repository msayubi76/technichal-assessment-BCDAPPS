<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pricing per city and ride category. All money is stored as whole
        // numbers in the smallest unit of the city's currency (paisa for PKR),
        // so we never get floating point rounding errors.
        Schema::create('fare_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->unsignedInteger('base_fare');
            $table->unsignedInteger('per_km_rate');
            $table->unsignedInteger('per_minute_rate');
            $table->unsignedInteger('minimum_fare');
            $table->decimal('commission_percent', 5, 2);

            // Average driving speed in the city, used to estimate trip time
            // and how long the driver needs to reach the rider.
            $table->unsignedSmallInteger('average_speed_kmh');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['city_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fare_rules');
    }
};
