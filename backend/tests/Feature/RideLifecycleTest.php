<?php

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\RideAssignment;
use App\Models\User;

beforeEach(function () {
    seedLahore();
    $this->rider = User::factory()->rider()->create();
    $this->driver = driverAt('MM Alam Road');
    $this->ride = requestedRide($this->rider);
});

function driverPost(User $user, string $url, array $body = [])
{
    return test()->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson($url, $body);
}

/**
 * Takes the ride through the driver's steps up to the given one:
 * accept, arrive at the pickup, start the trip.
 */
function driverStepsUpTo(string $lastStep, Ride $ride, User $driver): void
{
    foreach (['accept', 'arrive', 'start'] as $step) {
        driverPost($driver, "/api/rides/{$ride->id}/{$step}")->assertOk();

        if ($step === $lastStep) {
            return;
        }
    }
}

describe('accepting a ride', function () {
    it('assigns the driver and marks them as on a trip', function () {
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.driver.id', $this->driver->id)
            ->assertJsonPath('data.driver.plate_number', $this->driver->plate_number)
            ->assertJsonPath('data.rider.name', $this->rider->name);

        $ride = $this->ride->fresh();
        expect($ride->status)->toBe(RideStatus::Accepted)
            ->and($ride->driver_id)->toBe($this->driver->id)
            ->and($ride->accepted_at)->not->toBeNull()
            ->and($this->driver->fresh()->status)->toBe(DriverStatus::OnTrip);

        $assignment = RideAssignment::sole();
        expect($assignment->driver_id)->toBe($this->driver->id)
            ->and($assignment->distance_to_pickup_meters)->toBeGreaterThan(0);
    });

    it('refuses a second driver once the ride is taken', function () {
        $secondDriver = driverAt('Liberty Market');

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")->assertOk();

        driverPost($secondDriver->user, "/api/rides/{$this->ride->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'invalid_transition')
            ->assertJsonPath('error.details.from', 'accepted');

        expect($this->ride->fresh()->driver_id)->toBe($this->driver->id)
            ->and($secondDriver->fresh()->status)->toBe(DriverStatus::Available)
            ->and(RideAssignment::count())->toBe(1);
    });

    it('refuses an offline driver', function () {
        $this->driver->update(['status' => DriverStatus::Offline]);

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'driver_not_available');

        expect($this->ride->fresh()->status)->toBe(RideStatus::Requested);
    });

    it('refuses a driver who is already on another trip', function () {
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")->assertOk();

        driverAt('Liberty Market'); // keeps a driver nearby so the second ride can be requested
        $otherRide = requestedRide();

        driverPost($this->driver->user, "/api/rides/{$otherRide->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'driver_not_available');
    });

    it('refuses a driver of a different category', function () {
        $comfortDriver = driverAt('Liberty Market', ['category' => RideCategory::Comfort]);

        driverPost($comfortDriver->user, "/api/rides/{$this->ride->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'not_eligible');
    });

    it('does not let riders accept rides', function () {
        driverPost($this->rider, "/api/rides/{$this->ride->id}/accept")->assertForbidden();
    });

    it('returns 404 for a ride that does not exist', function () {
        driverPost($this->driver->user, '/api/rides/01JUNKJUNKJUNKJUNKJUNKJUNK/accept')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    });
});

describe('pickup and trip', function () {
    it('lets the driver mark arrival at the pickup', function () {
        driverStepsUpTo('accept', $this->ride, $this->driver->user);

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/arrive")
            ->assertOk()
            ->assertJsonPath('data.status', 'arrived')
            ->assertJsonPath('data.can_cancel', true);

        expect($this->ride->fresh()->arrived_at)->not->toBeNull()
            ->and($this->driver->fresh()->status)->toBe(DriverStatus::OnTrip);
    });

    it("lets the rider say they're coming once the driver has arrived", function () {
        driverStepsUpTo('arrive', $this->ride, $this->driver->user);

        driverPost($this->rider, "/api/rides/{$this->ride->id}/coming")
            ->assertOk()
            ->assertJsonPath('data.status', 'arrived');

        $firstTap = $this->ride->fresh()->rider_coming_at;
        expect($firstTap)->not->toBeNull();

        // Tapping again changes nothing.
        $this->travel(1)->minute();
        driverPost($this->rider, "/api/rides/{$this->ride->id}/coming")->assertOk();
        expect($this->ride->fresh()->rider_coming_at->equalTo($firstTap))->toBeTrue();
    });

    it("refuses \"I'm coming\" before the driver arrives", function () {
        driverStepsUpTo('accept', $this->ride, $this->driver->user);

        driverPost($this->rider, "/api/rides/{$this->ride->id}/coming")
            ->assertConflict()
            ->assertJsonPath('error.code', 'driver_not_arrived');

        expect($this->ride->fresh()->rider_coming_at)->toBeNull();
    });

    it("only lets the ride's own rider say they're coming", function () {
        driverStepsUpTo('arrive', $this->ride, $this->driver->user);
        $stranger = User::factory()->rider()->create();

        driverPost($stranger, "/api/rides/{$this->ride->id}/coming")->assertNotFound();
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/coming")->assertForbidden();
    });

    it("starts the trip without waiting for the rider's message", function () {
        driverStepsUpTo('arrive', $this->ride, $this->driver->user);

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.can_cancel', false);

        expect($this->ride->fresh()->started_at)->not->toBeNull();
    });

    it('cannot start the trip before reaching the pickup', function () {
        driverStepsUpTo('accept', $this->ride, $this->driver->user);

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/start")
            ->assertConflict()
            ->assertJsonPath('error.code', 'invalid_transition')
            ->assertJsonPath('error.message', 'The driver has not reached the pickup point yet.');
    });

    it('only lets the assigned driver take these steps', function () {
        driverStepsUpTo('accept', $this->ride, $this->driver->user);
        $otherDriver = driverAt('Liberty Market');

        driverPost($otherDriver->user, "/api/rides/{$this->ride->id}/arrive")->assertNotFound();
        driverPost($this->rider, "/api/rides/{$this->ride->id}/arrive")->assertForbidden();
        expect($this->ride->fresh()->status)->toBe(RideStatus::Accepted);
    });
});

describe('completing a ride', function () {
    it('finishes the trip and frees the driver at the drop-off point', function () {
        driverStepsUpTo('start', $this->ride, $this->driver->user);

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $driver = $this->driver->fresh();
        expect($this->ride->fresh()->completed_at)->not->toBeNull()
            ->and($driver->status)->toBe(DriverStatus::Available)
            ->and($driver->current_lat)->toBe($this->ride->dropoff_lat);
    });

    it('cannot complete a ride that was never accepted', function () {
        $otherDriver = driverAt('Liberty Market');
        // Not assigned to anyone, so no driver can see it to complete it.
        driverPost($otherDriver->user, "/api/rides/{$this->ride->id}/complete")->assertNotFound();
        expect($this->ride->fresh()->status)->toBe(RideStatus::Requested);
    });

    it('cannot complete a trip that has not started', function () {
        driverStepsUpTo('arrive', $this->ride, $this->driver->user);

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/complete")
            ->assertConflict()
            ->assertJsonPath('error.message', 'The trip has not started yet.');
    });

    it('cannot complete a ride twice', function () {
        driverStepsUpTo('start', $this->ride, $this->driver->user);
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/complete")->assertOk();

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/complete")
            ->assertConflict()
            ->assertJsonPath('error.code', 'invalid_transition');
    });

    it('only lets the assigned driver complete the ride', function () {
        $otherDriver = driverAt('Liberty Market');
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")->assertOk();

        driverPost($otherDriver->user, "/api/rides/{$this->ride->id}/complete")->assertNotFound();
        driverPost($this->rider, "/api/rides/{$this->ride->id}/complete")->assertForbidden();
    });
});

describe('cancelling a ride', function () {
    it('lets the rider cancel while waiting for a driver', function () {
        driverPost($this->rider, "/api/rides/{$this->ride->id}/cancel", ['reason' => 'Changed my plans'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_by', 'rider')
            ->assertJsonPath('data.can_cancel', false);
    });

    it('frees the driver when an accepted ride is cancelled', function () {
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")->assertOk();

        driverPost($this->rider, "/api/rides/{$this->ride->id}/cancel")->assertOk();

        expect($this->driver->fresh()->status)->toBe(DriverStatus::Available);
    });

    it('lets the assigned driver cancel', function () {
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")->assertOk();

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.cancelled_by', 'driver');
    });

    it('lets the rider or the driver cancel while the driver waits at the pickup', function (string $who) {
        driverStepsUpTo('arrive', $this->ride, $this->driver->user);
        $actor = $who === 'rider' ? $this->rider : $this->driver->user;

        driverPost($actor, "/api/rides/{$this->ride->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.cancelled_by', $who);

        expect($this->driver->fresh()->status)->toBe(DriverStatus::Available);
    })->with(['rider', 'driver']);

    it('cannot cancel once the trip has started', function (string $who) {
        driverStepsUpTo('start', $this->ride, $this->driver->user);
        $actor = $who === 'rider' ? $this->rider : $this->driver->user;

        driverPost($actor, "/api/rides/{$this->ride->id}/cancel")
            ->assertConflict()
            ->assertJsonPath('error.message', 'The trip has already started, so it can no longer be cancelled.');

        expect($this->ride->fresh()->status)->toBe(RideStatus::InProgress);
    })->with(['rider', 'driver']);

    it('cannot cancel a completed ride', function () {
        driverStepsUpTo('start', $this->ride, $this->driver->user);
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/complete")->assertOk();

        driverPost($this->rider, "/api/rides/{$this->ride->id}/cancel")
            ->assertConflict()
            ->assertJsonPath('error.code', 'invalid_transition')
            ->assertJsonPath('error.message', 'This ride is already completed.');
    });

    it('cannot accept a cancelled ride', function () {
        driverPost($this->rider, "/api/rides/{$this->ride->id}/cancel")->assertOk();

        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.message', 'This ride was cancelled.');
    });

    it("does not let another rider cancel someone else's ride", function () {
        $stranger = User::factory()->rider()->create();

        driverPost($stranger, "/api/rides/{$this->ride->id}/cancel")->assertNotFound();
        expect($this->ride->fresh()->status)->toBe(RideStatus::Requested);
    });
});

describe('viewing rides', function () {
    it('shows the rider their ride', function () {
        $this->actingAs($this->rider, 'sanctum')
            ->getJson("/api/rides/{$this->ride->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->ride->id)
            ->assertJsonMissingPath('data.fare.driver_earning');
    });

    it("hides other riders' rides behind a 404", function () {
        $stranger = User::factory()->rider()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/rides/{$this->ride->id}")
            ->assertNotFound();
    });

    it("returns the rider's current ride, or nothing", function () {
        $this->actingAs($this->rider, 'sanctum')
            ->getJson('/api/rides/current')
            ->assertOk()
            ->assertJsonPath('data.id', $this->ride->id);

        driverPost($this->rider, "/api/rides/{$this->ride->id}/cancel")->assertOk();

        $this->actingAs($this->rider, 'sanctum')
            ->getJson('/api/rides/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    });

    it('shows nearby drivers the waiting rides with their earnings', function () {
        $this->actingAs($this->driver->user, 'sanctum')
            ->getJson('/api/driver/rides/available')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->ride->id)
            ->assertJsonPath('data.0.can_cancel', false);

        $farDriver = driverAt('Bahria Town');

        $this->actingAs($farDriver->user, 'sanctum')
            ->getJson('/api/driver/rides/available')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('keeps a record of every status change', function () {
        driverStepsUpTo('arrive', $this->ride, $this->driver->user);
        driverPost($this->rider, "/api/rides/{$this->ride->id}/coming")->assertOk();
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/start")->assertOk();
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/complete")->assertOk();

        $trail = Ride::find($this->ride->id)->events
            ->map(fn ($event) => $event->meta['action'] ?? $event->to_status->value)
            ->all();

        expect($trail)->toBe(['requested', 'accepted', 'arrived', 'rider_coming', 'in_progress', 'completed']);
    });
});

describe('driver availability', function () {
    it('lets a driver go offline and back online', function () {
        $this->actingAs($this->driver->user, 'sanctum')
            ->patchJson('/api/driver/status', ['status' => 'offline'])
            ->assertOk()
            ->assertJsonPath('data.status', 'offline');

        $this->actingAs($this->driver->user, 'sanctum')
            ->patchJson('/api/driver/status', ['status' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.status', 'available');
    });

    it('does not let a driver go offline in the middle of a trip', function () {
        driverPost($this->driver->user, "/api/rides/{$this->ride->id}/accept")->assertOk();

        $this->actingAs($this->driver->user, 'sanctum')
            ->patchJson('/api/driver/status', ['status' => 'offline'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'driver_on_trip');
    });

    it('does not accept on_trip as a manual status', function () {
        $this->actingAs($this->driver->user, 'sanctum')
            ->patchJson('/api/driver/status', ['status' => 'on_trip'])
            ->assertUnprocessable();
    });
});
