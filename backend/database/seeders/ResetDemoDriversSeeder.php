<?php

namespace Database\Seeders;

use App\Enums\DriverStatus;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Puts the demo drivers back where they started, so a demo or the
 * end-to-end test always begins with drivers near every place.
 *
 * Drivers only move when they finish a trip, so after a few rides they
 * end up far from the usual pickup points. A driver who is in the middle
 * of a trip is left alone.
 *
 *   php artisan db:seed --class=ResetDemoDriversSeeder
 */
class ResetDemoDriversSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DemoUserSeeder::DRIVERS as [, $email, , , , , $lat, $lng, $status]) {
            $userId = User::where('email', $email)->value('id');

            Driver::where('user_id', $userId)
                ->where('status', '!=', DriverStatus::OnTrip)
                ->update([
                    'status' => $status,
                    'current_lat' => $lat,
                    'current_lng' => $lng,
                    'location_updated_at' => now(),
                ]);
        }
    }
}
