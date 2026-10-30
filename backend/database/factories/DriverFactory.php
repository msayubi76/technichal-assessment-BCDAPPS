<?php

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Models\City;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'city_id' => fn () => City::where('slug', 'lahore')->value('id'),
            'category' => RideCategory::Economy,
            'vehicle_model' => 'Suzuki Alto',
            'vehicle_color' => 'White',
            'plate_number' => strtoupper(fake()->unique()->bothify('LE?-####')),
            'status' => DriverStatus::Available,
            'current_lat' => 31.5104,
            'current_lng' => 74.3416,
            'location_updated_at' => now(),
            'rating' => 4.80,
        ];
    }

    public function at(float $lat, float $lng): static
    {
        return $this->state(['current_lat' => $lat, 'current_lng' => $lng]);
    }

    public function comfort(): static
    {
        return $this->state(['category' => RideCategory::Comfort]);
    }

    public function offline(): static
    {
        return $this->state(['status' => DriverStatus::Offline]);
    }
}
