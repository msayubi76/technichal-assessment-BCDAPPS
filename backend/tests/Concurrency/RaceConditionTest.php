<?php

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Exceptions\ApiException;
use App\Models\Ride;
use App\Models\RideAssignment;
use App\Models\User;
use App\Services\Rides\RideService;
use Illuminate\Support\Facades\DB;

/*
| These tests start real separate processes, each with its own database
| connection, and release them all at the same instant. That is what happens
| when several phones hit the API at once, and it is the only honest way to
| prove the locking works. A normal single-process test runs one request at a
| time and would pass even with no locking at all.
*/

/**
 * Runs every task in its own process, all starting together.
 * Returns what each task ended with: "ok" or the error code it got.
 *
 * @param  list<callable>  $tasks
 * @return list<string>
 */
function runAtTheSameTime(array $tasks): array
{
    // Each process must open its own database connection.
    DB::disconnect();

    $startAt = microtime(true) + 0.5;
    $children = [];

    foreach ($tasks as $task) {
        $resultFile = tempnam(sys_get_temp_dir(), 'race');
        $pid = pcntl_fork();

        if ($pid === 0) {
            // Child process: wait for the starting gun, run, report, exit.
            while (microtime(true) < $startAt) {
                usleep(100);
            }

            try {
                $task();
                $result = 'ok';
            } catch (ApiException $e) {
                $result = $e->errorCode;
            } catch (Throwable $e) {
                $result = 'crashed: '.$e->getMessage();
            }

            file_put_contents($resultFile, $result);

            // Exit hard, so the child doesn't run the test framework's
            // shutdown code a second time.
            posix_kill(getmypid(), SIGKILL);
        }

        $children[] = [$pid, $resultFile];
    }

    $results = [];
    foreach ($children as [$pid, $resultFile]) {
        pcntl_waitpid($pid, $status);
        $results[] = (string) file_get_contents($resultFile);
        unlink($resultFile);
    }

    return $results;
}

beforeEach(function () {
    seedLahore();
});

it('lets only one of five drivers accept the same ride', function () {
    $drivers = collect(range(1, 5))->map(fn () => driverAt('MM Alam Road'));
    $ride = requestedRide();

    $results = runAtTheSameTime(
        $drivers->map(fn ($driver) => fn () => app(RideService::class)->accept($ride->id, $driver))->all(),
    );

    expect(array_count_values($results))->toEqual(['ok' => 1, 'invalid_transition' => 4]);

    $ride->refresh();
    expect($ride->status)->toBe(RideStatus::Accepted)
        ->and(RideAssignment::where('ride_id', $ride->id)->count())->toBe(1)
        ->and($drivers->filter(fn ($d) => $d->fresh()->status === DriverStatus::OnTrip)->count())->toBe(1)
        ->and($drivers->first(fn ($d) => $d->fresh()->status === DriverStatus::OnTrip)->id)->toBe($ride->driver_id);
});

it('lets one driver take only one of two rides tapped at the same moment', function () {
    $driver = driverAt('MM Alam Road');
    $firstRide = requestedRide();
    $secondRide = requestedRide();

    $results = runAtTheSameTime([
        fn () => app(RideService::class)->accept($firstRide->id, $driver),
        fn () => app(RideService::class)->accept($secondRide->id, $driver),
    ]);

    sort($results);
    expect($results)->toBe(['driver_not_available', 'ok'])
        ->and(Ride::where('status', RideStatus::Accepted)->count())->toBe(1)
        ->and(Ride::where('status', RideStatus::Requested)->count())->toBe(1)
        ->and($driver->fresh()->status)->toBe(DriverStatus::OnTrip);
});

it('creates only one ride when a rider double-taps request', function () {
    driverAt('MM Alam Road');
    $rider = User::factory()->rider()->create();

    $request = fn () => app(RideService::class)->request(
        $rider, RideCategory::Economy, place('Liberty Market'), place('Packages Mall'),
    );

    $results = runAtTheSameTime([$request, $request, $request]);

    expect(array_count_values($results))->toEqual(['ok' => 1, 'active_ride_exists' => 2])
        ->and(Ride::where('rider_id', $rider->id)->count())->toBe(1);
});

it('ends in a clean state when the rider cancels just as a driver accepts', function () {
    $driver = driverAt('MM Alam Road');
    $rider = User::factory()->rider()->create();
    $ride = requestedRide($rider);

    runAtTheSameTime([
        fn () => app(RideService::class)->accept($ride->id, $driver),
        fn () => app(RideService::class)->cancel($ride->id, $rider, 'Changed my plans'),
    ]);

    // Whichever ran first, the ride ends cancelled and the driver is free.
    expect($ride->fresh()->status)->toBe(RideStatus::Cancelled)
        ->and($driver->fresh()->status)->toBe(DriverStatus::Available);
});
