<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            // ULIDs are hard to guess and still sort by creation time.
            $table->ulid('id')->primary();
            $table->foreignId('rider_id')->constrained('users');
            $table->foreignId('city_id')->constrained();
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->string('category', 20);

            // requested, accepted, completed or cancelled
            $table->string('status', 20);

            $table->string('pickup_name');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('dropoff_name');
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);

            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('pickup_eta_seconds')->nullable();

            // The fare is worked out once when the ride is requested and saved
            // with every part shown, so the rider is charged what they were quoted.
            // Amounts are in the smallest currency unit (paisa for PKR).
            $table->char('currency', 3);
            $table->unsignedInteger('base_fare');
            $table->unsignedInteger('distance_fare');
            $table->unsignedInteger('time_fare');
            $table->decimal('surge_multiplier', 4, 2)->default(1.00);
            $table->unsignedInteger('total_fare');
            $table->unsignedInteger('commission_amount');
            $table->unsignedInteger('driver_earning');

            $table->timestamp('requested_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 20)->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            // Safety nets in the database itself, in case application code
            // ever gets it wrong:
            // - a rider can only have one ride in progress at a time
            // - a driver can only be on one accepted ride at a time
            // MySQL has no partial unique index, so these columns hold the id
            // only while the ride is active and NULL otherwise. NULLs never clash.
            $table->unsignedBigInteger('active_rider_id')->nullable()
                ->storedAs("CASE WHEN status IN ('requested', 'accepted') THEN rider_id END");
            $table->unsignedBigInteger('active_driver_id')->nullable()
                ->storedAs("CASE WHEN status = 'accepted' THEN driver_id END");
            $table->unique('active_rider_id');
            $table->unique('active_driver_id');

            $table->index(['rider_id', 'created_at']);
            $table->index(['city_id', 'status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
