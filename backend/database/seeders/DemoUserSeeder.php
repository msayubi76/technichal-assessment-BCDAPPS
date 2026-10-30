<?php

namespace Database\Seeders;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for trying the app locally. All use the password "password".
 *
 * Existing accounts are left alone, so restarting the containers never
 * resets a driver who is in the middle of a trip.
 */
class DemoUserSeeder extends Seeder
{
    /**
     * Drivers parked around the city, so every place has someone nearby.
     * ResetDemoDriversSeeder sends idle drivers back to these spots.
     */
    public const DRIVERS = [
        ['Usman Tariq', 'driver1@example.com', RideCategory::Economy, 'Suzuki Alto', 'White', 'LEA-1021', 31.5120, 74.3440, DriverStatus::Available],
        ['Hamza Ali', 'driver2@example.com', RideCategory::Economy, 'Suzuki WagonR', 'Silver', 'LEB-2234', 31.4700, 74.2700, DriverStatus::Available],
        ['Fatima Noor', 'driver3@example.com', RideCategory::Economy, 'Suzuki Cultus', 'Grey', 'LEC-3345', 31.5800, 74.3150, DriverStatus::Available],
        ['Imran Shah', 'driver4@example.com', RideCategory::Economy, 'Kia Picanto', 'Red', 'LED-4456', 31.4800, 74.4050, DriverStatus::Available],
        ['Zainab Raza', 'driver5@example.com', RideCategory::Comfort, 'Toyota Corolla', 'Black', 'LEE-5567', 31.5180, 74.3520, DriverStatus::Available],
        ['Ahmed Butt', 'driver6@example.com', RideCategory::Comfort, 'Honda City', 'White', 'LEF-6678', 31.4720, 74.3540, DriverStatus::Available],
        ['Saad Malik', 'driver7@example.com', RideCategory::Comfort, 'Honda Civic', 'Blue', 'LEG-7789', 31.5300, 74.3950, DriverStatus::Available],
        ['Kashif Iqbal', 'driver8@example.com', RideCategory::Economy, 'Toyota Yaris', 'Silver', 'LEH-8890', 31.5000, 74.3300, DriverStatus::Offline],
    ];

    public function run(): void
    {
        $lahore = City::where('slug', 'lahore')->firstOrFail();

        $riders = [
            ['Ayesha Khan', 'rider@example.com', '+923001110001'],
            ['Bilal Ahmed', 'rider2@example.com', '+923001110002'],
        ];

        foreach ($riders as [$name, $email, $phone]) {
            User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'phone' => $phone,
                'role' => UserRole::Rider,
                'password' => 'password',
            ]);
        }

        foreach (self::DRIVERS as $i => [$name, $email, $category, $model, $color, $plate, $lat, $lng, $status]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'phone' => sprintf('+92300222%04d', $i + 1),
                'role' => UserRole::Driver,
                'password' => 'password',
            ]);

            $user->driver()->firstOrCreate([], [
                'city_id' => $lahore->id,
                'category' => $category,
                'vehicle_model' => $model,
                'vehicle_color' => $color,
                'plate_number' => $plate,
                'status' => $status,
                'current_lat' => $lat,
                'current_lng' => $lng,
                'location_updated_at' => now(),
                'rating' => 4.80,
            ]);
        }
    }
}
