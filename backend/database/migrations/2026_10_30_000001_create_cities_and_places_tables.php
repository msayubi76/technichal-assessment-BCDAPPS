<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each city we operate in. Adding a new city is a new row here plus
        // its fare rules, no code change needed.
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('country_code', 2);
            $table->char('currency', 3);
            $table->string('timezone');
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lng', 10, 7);

            // A simple rectangle around the service area. Good enough to say
            // "we don't operate here yet". A real polygon can replace it later.
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 10, 7);
            $table->decimal('max_lng', 10, 7);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Popular pickup and drop-off spots, so riders can pick a place
        // instead of typing coordinates.
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('area')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();

            $table->unique(['city_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
        Schema::dropIfExists('cities');
    }
};
