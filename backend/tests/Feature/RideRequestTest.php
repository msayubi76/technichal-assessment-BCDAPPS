<?php

use App\Models\Ride;
use App\Models\RideEvent;
use App\Models\User;

beforeEach(function () {
    seedLahore();
    $this->rider = User::factory()->rider()->create();
});

function rideRequestBody(string $from = 'Liberty Market', string $to = 'Packages Mall', string $category = 'economy'): array
{
    return ['category' => $category, 'pickup' => place($from), 'dropoff' => place($to)];
}

it('creates a ride with a server-side fare when a driver is nearby', function () {
    driverAt('MM Alam Road');

    $response = $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', rideRequestBody());

    $response->assertCreated()
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.category', 'economy')
        ->assertJsonPath('data.pickup.name', 'Liberty Market')
        ->assertJsonPath('data.dropoff.name', 'Packages Mall')
        ->assertJsonPath('data.fare.currency', 'PKR')
        ->assertJsonPath('data.can_cancel', true);

    $ride = Ride::sole();
    expect($ride->rider_id)->toBe($this->rider->id)
        ->and($ride->total_fare)->toBeGreaterThanOrEqual(20000)
        ->and($ride->pickup_eta_seconds)->toBeGreaterThan(0)
        ->and(RideEvent::where('ride_id', $ride->id)->value('to_status')->value)->toBe('requested');
});

it('ignores any fare the app tries to send', function () {
    driverAt('MM Alam Road');

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', rideRequestBody() + ['total_fare' => 1])
        ->assertCreated();

    expect(Ride::sole()->total_fare)->toBeGreaterThan(1);
});

it('rejects missing or invalid input with field errors', function () {
    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', [
            'category' => 'helicopter',
            'pickup' => ['name' => '', 'lat' => 200, 'lng' => 74.3],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['details' => [
            'category', 'pickup.name', 'pickup.lat', 'dropoff',
        ]]]);

    expect(Ride::count())->toBe(0);
});

it('refuses pickups outside the cities we serve', function () {
    driverAt('MM Alam Road');
    $karachi = ['name' => 'Clifton, Karachi', 'lat' => 24.8138, 'lng' => 67.0300];

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', ['category' => 'economy', 'pickup' => $karachi, 'dropoff' => place('Packages Mall')])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'outside_service_area');
});

it('refuses a trip where pickup and destination are the same place', function () {
    driverAt('MM Alam Road');

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', rideRequestBody('Liberty Market', 'Liberty Market'))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'trip_too_short');
});

it('tells the rider when no driver is nearby', function () {
    // Only driver is far away in Bahria Town, another is close but offline.
    driverAt('Bahria Town');
    driverAt('MM Alam Road', ['status' => 'offline']);

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', rideRequestBody())
        ->assertConflict()
        ->assertJsonPath('error.code', 'no_drivers_available');

    expect(Ride::count())->toBe(0);
});

it('only matches drivers of the requested category', function () {
    driverAt('MM Alam Road'); // economy only

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', rideRequestBody(category: 'comfort'))
        ->assertConflict()
        ->assertJsonPath('error.code', 'no_drivers_available');
});

it('does not allow a second ride while one is in progress', function () {
    driverAt('MM Alam Road');
    requestedRide($this->rider);

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', rideRequestBody())
        ->assertConflict()
        ->assertJsonPath('error.code', 'active_ride_exists');

    expect(Ride::count())->toBe(1);
});

it('does not let drivers request rides', function () {
    $driver = driverAt('MM Alam Road');

    $this->actingAs($driver->user, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', rideRequestBody())
        ->assertForbidden();
});

it('quotes every category before booking', function () {
    driverAt('MM Alam Road');

    $response = $this->actingAs($this->rider, 'sanctum')
        ->postJson('/api/rides/estimate', [
            'pickup' => place('Liberty Market'),
            'dropoff' => place('Packages Mall'),
        ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.category', 'economy')
        ->assertJsonPath('data.0.drivers_available', true)
        ->assertJsonPath('data.1.category', 'comfort')
        ->assertJsonPath('data.1.drivers_available', false)
        ->assertJsonPath('data.1.pickup_eta_seconds', null);

    expect($response->json('data.1.total_fare'))->toBeGreaterThan($response->json('data.0.total_fare'));
});

it('lists the cities and places for the pickers', function () {
    $this->actingAs($this->rider, 'sanctum')
        ->getJson('/api/cities')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Lahore')
        ->assertJsonCount(14, 'data.0.places');
});
