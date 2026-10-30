<?php

use App\Enums\RideCategory;
use App\Models\Driver;
use App\Models\Place;
use App\Models\Ride;
use App\Models\User;
use App\Services\Rides\RideService;
use Database\Seeders\LahoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

// The concurrency tests run work in separate processes, which can only see
// data that is really committed. So these tests empty the tables instead of
// wrapping each test in a transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
| Helpers shared by the ride tests.
*/

function seedLahore(): void
{
    (new LahoreSeeder)->run();
}

/**
 * A seeded Lahore place as the API expects it: ['name', 'lat', 'lng'].
 *
 * @return array{name: string, lat: float, lng: float}
 */
function place(string $name): array
{
    $place = Place::where('name', $name)->firstOrFail();

    return ['name' => $place->name, 'lat' => $place->lat, 'lng' => $place->lng];
}

/**
 * An available driver parked at one of the seeded places.
 */
function driverAt(string $placeName, array $attributes = []): Driver
{
    $place = place($placeName);

    return Driver::factory()->at($place['lat'], $place['lng'])->create($attributes);
}

/**
 * Creates a waiting ride through the real service, the same way the API does.
 */
function requestedRide(?User $rider = null, string $from = 'Liberty Market', string $to = 'Packages Mall', RideCategory $category = RideCategory::Economy): Ride
{
    return app(RideService::class)->request(
        $rider ?? User::factory()->rider()->create(),
        $category,
        place($from),
        place($to),
    );
}

function idempotencyKey(): string
{
    return (string) Str::uuid();
}
