<?php

namespace Database\Seeders;

use App\Enums\RideCategory;
use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * Sets up Lahore as a service city with its prices and popular places.
 * Safe to run again: rows are updated in place, never duplicated.
 */
class LahoreSeeder extends Seeder
{
    public function run(): void
    {
        $lahore = City::updateOrCreate(['slug' => 'lahore'], [
            'name' => 'Lahore',
            'country_code' => 'PK',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'center_lat' => 31.5204,
            'center_lng' => 74.3587,
            'min_lat' => 31.3000,
            'max_lat' => 31.7000,
            'min_lng' => 74.1500,
            'max_lng' => 74.5500,
            'is_active' => true,
        ]);

        // Prices are in paisa (100 paisa = 1 rupee).
        $fares = [
            RideCategory::Economy->value => [
                'base_fare' => 10000,         // Rs 100
                'per_km_rate' => 3500,        // Rs 35 per km
                'per_minute_rate' => 500,     // Rs 5 per minute
                'minimum_fare' => 20000,      // Rs 200
                'commission_percent' => 15,
                'average_speed_kmh' => 22,
            ],
            RideCategory::Comfort->value => [
                'base_fare' => 15000,         // Rs 150
                'per_km_rate' => 5000,        // Rs 50 per km
                'per_minute_rate' => 800,     // Rs 8 per minute
                'minimum_fare' => 30000,      // Rs 300
                'commission_percent' => 18,
                'average_speed_kmh' => 22,
            ],
        ];

        foreach ($fares as $category => $rule) {
            $lahore->fareRules()->updateOrCreate(
                ['category' => $category],
                $rule + ['is_active' => true],
            );
        }

        $places = [
            ['Liberty Market', 'Gulberg', 31.5104, 74.3416],
            ['MM Alam Road', 'Gulberg', 31.5155, 74.3505],
            ['Fortress Stadium', 'Cantt', 31.5326, 74.3693],
            ['Allama Iqbal International Airport', 'Airport', 31.5216, 74.4036],
            ['Packages Mall', 'Walton', 31.4710, 74.3555],
            ['Emporium Mall', 'Johar Town', 31.4672, 74.2659],
            ['LUMS', 'DHA', 31.4707, 74.4097],
            ['Model Town Park', 'Model Town', 31.4834, 74.3263],
            ['University of the Punjab', 'New Campus', 31.4815, 74.3031],
            ['Badshahi Mosque', 'Walled City', 31.5882, 74.3101],
            ['Minar-e-Pakistan', 'Greater Iqbal Park', 31.5925, 74.3095],
            ['Anarkali Bazaar', 'Old City', 31.5687, 74.3120],
            ['Lahore Railway Station', 'Railway Station', 31.5772, 74.3363],
            ['Bahria Town', 'Bahria Town', 31.3695, 74.1808],
        ];

        foreach ($places as [$name, $area, $lat, $lng]) {
            $lahore->places()->updateOrCreate(
                ['name' => $name],
                ['area' => $area, 'lat' => $lat, 'lng' => $lng],
            );
        }
    }
}
