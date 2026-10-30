<?php

use App\Events\RideRequested;
use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    seedLahore();
    $this->rider = User::factory()->rider()->create();
});

it('tells nearby drivers about a new ride, and no one else', function () {
    Event::fake([RideRequested::class]);
    $nearby = driverAt('MM Alam Road');
    $faraway = driverAt('Bahria Town');

    requestedRide($this->rider);

    Event::assertDispatched(RideRequested::class, fn ($event) => $event->driverId === $nearby->id);
    Event::assertNotDispatched(RideRequested::class, fn ($event) => $event->driverId === $faraway->id);
});

it('announces each status change on the ride channel', function () {
    Event::fake([RideStatusChanged::class]);
    $driver = driverAt('MM Alam Road');
    $ride = requestedRide($this->rider);

    $this->actingAs($driver->user, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson("/api/rides/{$ride->id}/accept")
        ->assertOk();

    Event::assertDispatched(RideStatusChanged::class, function (RideStatusChanged $event) use ($ride) {
        return $event->rideId === $ride->id
            && $event->status === 'accepted'
            && $event->broadcastOn()[0]->name === "private-rides.{$ride->id}";
    });
});

it('does not announce anything when a change is refused', function () {
    Event::fake([RideStatusChanged::class]);
    $driver = driverAt('MM Alam Road');
    $ride = requestedRide($this->rider);

    $this->actingAs($driver->user, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson("/api/rides/{$ride->id}/complete")
        ->assertNotFound();

    Event::assertNotDispatched(RideStatusChanged::class);
});

it('still saves the ride when the WebSocket server is down', function () {
    // Point broadcasting at a port where nothing is listening.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1,
    ]);
    driverAt('MM Alam Road');

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', [
            'category' => 'economy',
            'pickup' => place('Liberty Market'),
            'dropoff' => place('Packages Mall'),
        ])
        ->assertCreated();

    expect(Ride::count())->toBe(1);
});

describe('channel access', function () {
    beforeEach(function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        // Channel rules attach to the broadcaster that was active when the app
        // started (none, in tests), so load them again for Reverb.
        require base_path('routes/channels.php');

        driverAt('MM Alam Road');
        $this->ride = requestedRide($this->rider);
    });

    function joinChannel(User $user, string $channel)
    {
        return test()->actingAs($user, 'sanctum')->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-{$channel}",
        ]);
    }

    it('lets the rider listen to their own ride', function () {
        joinChannel($this->rider, "rides.{$this->ride->id}")->assertOk();
    });

    it("blocks other riders from listening to someone else's ride", function () {
        joinChannel(User::factory()->rider()->create(), "rides.{$this->ride->id}")->assertForbidden();
    });

    it('lets a driver listen only to their own driver channel', function () {
        $driver = driverAt('Liberty Market');
        $otherDriver = driverAt('Fortress Stadium');

        joinChannel($driver->user, "drivers.{$driver->id}")->assertOk();
        joinChannel($driver->user, "drivers.{$otherDriver->id}")->assertForbidden();
    });
});
