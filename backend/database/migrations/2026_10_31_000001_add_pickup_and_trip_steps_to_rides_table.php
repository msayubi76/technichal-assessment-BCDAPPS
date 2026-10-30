<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the steps between accepting and completing a ride: the driver arrives
 * at the pickup, the rider says they are coming, and the trip starts.
 *
 * The two "active" safety-net columns now also cover the new statuses, so a
 * rider or driver still can't have two rides in progress at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('accepted_at');
            $table->timestamp('rider_coming_at')->nullable()->after('arrived_at');
            $table->timestamp('started_at')->nullable()->after('rider_coming_at');
        });

        $this->rebuildActiveColumns(
            riderStatuses: "'requested', 'accepted', 'arrived', 'in_progress'",
            driverStatuses: "'accepted', 'arrived', 'in_progress'",
        );
    }

    public function down(): void
    {
        $this->rebuildActiveColumns(
            riderStatuses: "'requested', 'accepted'",
            driverStatuses: "'accepted'",
        );

        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['arrived_at', 'rider_coming_at', 'started_at']);
        });
    }

    /**
     * A generated column's formula can't be edited in place together with its
     * unique index, so drop both and add them back with the new list.
     */
    private function rebuildActiveColumns(string $riderStatuses, string $driverStatuses): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropUnique(['active_rider_id']);
            $table->dropUnique(['active_driver_id']);
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['active_rider_id', 'active_driver_id']);
        });

        Schema::table('rides', function (Blueprint $table) use ($riderStatuses, $driverStatuses) {
            $table->unsignedBigInteger('active_rider_id')->nullable()
                ->storedAs("CASE WHEN status IN ({$riderStatuses}) THEN rider_id END");
            $table->unsignedBigInteger('active_driver_id')->nullable()
                ->storedAs("CASE WHEN status IN ({$driverStatuses}) THEN driver_id END");
            $table->unique('active_rider_id');
            $table->unique('active_driver_id');
        });
    }
};
