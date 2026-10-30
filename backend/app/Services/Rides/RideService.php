<?php

namespace App\Services\Rides;

use App\Enums\ActorType;
use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Exceptions\ApiException;
use App\Models\City;
use App\Models\Driver;
use App\Models\FareRule;
use App\Models\Ride;
use App\Models\RideAssignment;
use App\Models\RideEvent;
use App\Models\User;
use App\Services\Dispatch\DriverMatcher;
use App\Support\Geo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Everything that happens to a ride: quote, request, accept, arrive, start,
 * cancel, complete.
 *
 * Every change runs inside a database transaction. When a change touches both
 * a ride and a driver, the ride row is locked first and the driver row second,
 * always in that order, so two requests can never end up waiting on each other.
 */
class RideService
{
    public function __construct(
        private readonly CityResolver $cities,
        private readonly FareCalculator $fares,
        private readonly DriverMatcher $matcher,
        private readonly RideStateMachine $stateMachine,
        private readonly RideNotifier $notifier,
    ) {}

    /**
     * Price and pickup time for every ride category, before the rider books.
     *
     * @param  array{name: string, lat: float, lng: float}  $pickup
     * @param  array{name: string, lat: float, lng: float}  $dropoff
     * @return list<array<string, mixed>>
     */
    public function estimate(array $pickup, array $dropoff): array
    {
        $city = $this->cities->forTrip($pickup, $dropoff);
        $this->assertTripIsLongEnough($pickup, $dropoff);

        $rules = $city->fareRules()->where('is_active', true)->orderBy('id')->get();

        return $rules->map(function (FareRule $rule) use ($city, $pickup, $dropoff) {
            $quote = $this->fares->quote($rule, $pickup, $dropoff, $city->currency);
            $nearest = $this->matcher
                ->nearestAvailable($city->id, $rule->category, $pickup['lat'], $pickup['lng'], 1)
                ->first();

            return [
                'category' => $rule->category->value,
                'label' => $rule->category->label(),
                'currency' => $quote->currency,
                'total_fare' => $quote->totalFare,
                'distance_meters' => $quote->distanceMeters,
                'duration_seconds' => $quote->durationSeconds,
                'drivers_available' => $nearest !== null,
                'pickup_eta_seconds' => $nearest === null ? null : $this->pickupEtaSeconds((int) $nearest->distance_meters, $rule),
            ];
        })->values()->all();
    }

    /**
     * A rider asks for a ride. The fare is worked out here on the server,
     * never taken from the app, and nearby drivers are told about it.
     *
     * @param  array{name: string, lat: float, lng: float}  $pickup
     * @param  array{name: string, lat: float, lng: float}  $dropoff
     */
    public function request(User $rider, RideCategory $category, array $pickup, array $dropoff): Ride
    {
        $city = $this->cities->forTrip($pickup, $dropoff);
        $this->assertTripIsLongEnough($pickup, $dropoff);
        $rule = $this->fareRule($city, $category);

        if ($rider->rides()->active()->exists()) {
            throw $this->activeRideExists();
        }

        $drivers = $this->matcher->nearestAvailable(
            $city->id, $category, $pickup['lat'], $pickup['lng'], config('rides.notify_driver_count'),
        );

        if ($drivers->isEmpty()) {
            throw new ApiException('no_drivers_available', 'No drivers are available near you right now. Please try again in a few minutes.', 409);
        }

        $quote = $this->fares->quote($rule, $pickup, $dropoff, $city->currency);

        try {
            $ride = DB::transaction(function () use ($rider, $city, $category, $pickup, $dropoff, $quote, $drivers, $rule) {
                $ride = Ride::create([
                    'rider_id' => $rider->id,
                    'city_id' => $city->id,
                    'category' => $category,
                    'status' => RideStatus::Requested,
                    'pickup_name' => $pickup['name'],
                    'pickup_lat' => $pickup['lat'],
                    'pickup_lng' => $pickup['lng'],
                    'dropoff_name' => $dropoff['name'],
                    'dropoff_lat' => $dropoff['lat'],
                    'dropoff_lng' => $dropoff['lng'],
                    'pickup_eta_seconds' => $this->pickupEtaSeconds((int) $drivers->first()->distance_meters, $rule),
                    'requested_at' => now(),
                ] + $quote->toRideAttributes());

                $this->recordEvent($ride, null, ActorType::Rider, $rider->id);

                return $ride;
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests from the same rider got past the check above at the
            // same moment. The database allowed only one; this is the other.
            throw $this->activeRideExists();
        }

        Log::info('ride.requested', ['ride_id' => $ride->id, 'drivers_notified' => $drivers->count()]);
        $this->notifier->offerToDrivers($ride, $drivers);

        return $ride;
    }

    /**
     * A driver accepts a waiting ride. If several drivers tap accept at the
     * same moment, the row lock makes them take turns: the first one wins,
     * the others find the ride already accepted and get an error.
     */
    public function accept(string $rideId, Driver $driver): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $driver) {
            $ride = Ride::whereKey($rideId)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanMove($ride, RideStatus::Accepted);

            if ($driver->status !== DriverStatus::Available) {
                throw new ApiException('driver_not_available', 'You need to be online and free to accept a ride.', 409);
            }

            if ((int) $driver->city_id !== (int) $ride->city_id || $driver->category !== $ride->category) {
                throw new ApiException('not_eligible', 'This ride is for a different city or ride category.', 403);
            }

            $distanceToPickup = $driver->current_lat === null ? null : Geo::distanceMeters(
                $driver->current_lat, $driver->current_lng, $ride->pickup_lat, $ride->pickup_lng,
            );
            $eta = $distanceToPickup === null ? null : $this->pickupEtaSeconds(
                $distanceToPickup, $this->fareRule($ride->city, $ride->category),
            );

            $from = $ride->status;

            $ride->update([
                'status' => RideStatus::Accepted,
                'driver_id' => $driver->id,
                'accepted_at' => now(),
                'pickup_eta_seconds' => $eta,
            ]);

            $driver->update(['status' => DriverStatus::OnTrip]);

            RideAssignment::create([
                'ride_id' => $ride->id,
                'driver_id' => $driver->id,
                'driver_lat' => $driver->current_lat,
                'driver_lng' => $driver->current_lng,
                'distance_to_pickup_meters' => $distanceToPickup,
                'pickup_eta_seconds' => $eta,
                'accepted_at' => $ride->accepted_at,
            ]);

            $this->recordEvent($ride, $from, ActorType::Driver, $driver->user_id);

            return $ride;
        });

        Log::info('ride.accepted', ['ride_id' => $ride->id, 'driver_id' => $driver->id]);
        $this->notifier->statusChanged($ride);

        return $ride;
    }

    /**
     * The assigned driver has reached the pickup point.
     */
    public function arrive(string $rideId, Driver $driver): Ride
    {
        return $this->moveAsDriver($rideId, $driver, RideStatus::Arrived, 'arrived_at', 'ride.driver_arrived');
    }

    /**
     * The rider is in the car and the driver sets off to the destination.
     * This doesn't wait for the rider's "I'm coming": that is only a message.
     */
    public function start(string $rideId, Driver $driver): Ride
    {
        return $this->moveAsDriver($rideId, $driver, RideStatus::InProgress, 'started_at', 'ride.started');
    }

    /**
     * The rider tells the waiting driver they are on their way out. It doesn't
     * change the ride's status, so tapping it twice does no harm.
     */
    public function riderComing(string $rideId, User $rider): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $rider) {
            $ride = Ride::whereKey($rideId)->where('rider_id', $rider->id)->lockForUpdate()->firstOrFail();

            if ($ride->status !== RideStatus::Arrived) {
                throw new ApiException('driver_not_arrived', 'You can let the driver know once they have arrived.', 409);
            }

            if ($ride->rider_coming_at === null) {
                $ride->update(['rider_coming_at' => now()]);
                $this->recordEvent($ride, $ride->status, ActorType::Rider, $rider->id, ['action' => 'rider_coming']);
            }

            return $ride;
        });

        $this->notifier->statusChanged($ride);

        return $ride;
    }

    /**
     * The rider can cancel until the trip starts. The assigned driver can
     * cancel after accepting, for example when the rider doesn't show up.
     * Either way the driver becomes free for new rides again.
     */
    public function cancel(string $rideId, User $actor, ?string $reason): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $actor, $reason) {
            $ride = Ride::whereKey($rideId)->visibleTo($actor)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanMove($ride, RideStatus::Cancelled);

            $actorType = $actor->isRider() ? ActorType::Rider : ActorType::Driver;
            $from = $ride->status;

            $ride->update([
                'status' => RideStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actorType,
                'cancellation_reason' => $reason,
            ]);

            if ($ride->driver_id !== null) {
                Driver::whereKey($ride->driver_id)->lockForUpdate()->firstOrFail()
                    ->update(['status' => DriverStatus::Available]);
            }

            $this->recordEvent($ride, $from, $actorType, $actor->id, $reason ? ['reason' => $reason] : null);

            return $ride;
        });

        Log::info('ride.cancelled', ['ride_id' => $ride->id, 'by' => $ride->cancelled_by->value]);
        $this->notifier->statusChanged($ride);

        return $ride;
    }

    /**
     * The assigned driver finishes the trip. The driver is now at the
     * drop-off point and free for the next ride.
     */
    public function complete(string $rideId, Driver $driver): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $driver) {
            $ride = Ride::whereKey($rideId)->where('driver_id', $driver->id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanMove($ride, RideStatus::Completed);

            $from = $ride->status;

            $ride->update([
                'status' => RideStatus::Completed,
                'completed_at' => now(),
            ]);

            $driver->update([
                'status' => DriverStatus::Available,
                'current_lat' => $ride->dropoff_lat,
                'current_lng' => $ride->dropoff_lng,
                'location_updated_at' => now(),
            ]);

            $this->recordEvent($ride, $from, ActorType::Driver, $driver->user_id);

            return $ride;
        });

        Log::info('ride.completed', ['ride_id' => $ride->id, 'driver_id' => $driver->id]);
        $this->notifier->statusChanged($ride);

        return $ride;
    }

    /**
     * A step only the assigned driver can take, and that doesn't change the
     * driver's own status (they stay on the trip).
     */
    private function moveAsDriver(string $rideId, Driver $driver, RideStatus $to, string $timestampColumn, string $logEvent): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $driver, $to, $timestampColumn) {
            $ride = Ride::whereKey($rideId)->where('driver_id', $driver->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanMove($ride, $to);

            $from = $ride->status;
            $ride->update(['status' => $to, $timestampColumn => now()]);
            $this->recordEvent($ride, $from, ActorType::Driver, $driver->user_id);

            return $ride;
        });

        Log::info($logEvent, ['ride_id' => $ride->id, 'driver_id' => $driver->id]);
        $this->notifier->statusChanged($ride);

        return $ride;
    }

    /**
     * @param  array{lat: float, lng: float}  $pickup
     * @param  array{lat: float, lng: float}  $dropoff
     */
    private function assertTripIsLongEnough(array $pickup, array $dropoff): void
    {
        $meters = Geo::distanceMeters($pickup['lat'], $pickup['lng'], $dropoff['lat'], $dropoff['lng']);

        if ($meters < config('rides.minimum_trip_meters')) {
            throw new ApiException('trip_too_short', 'Pickup and destination are too close together.');
        }
    }

    private function fareRule(City $city, RideCategory $category): FareRule
    {
        $rule = $city->fareRules()->where('category', $category)->where('is_active', true)->first();

        if ($rule === null) {
            throw new ApiException('category_unavailable', "{$category->label()} rides are not available in {$city->name}.");
        }

        return $rule;
    }

    private function pickupEtaSeconds(int $straightMeters, FareRule $rule): int
    {
        $roadMeters = (int) round($straightMeters * config('rides.road_distance_factor'));

        return $this->fares->travelSeconds($roadMeters, $rule->average_speed_kmh);
    }

    private function activeRideExists(): ApiException
    {
        return new ApiException('active_ride_exists', 'You already have a ride in progress.', 409);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function recordEvent(Ride $ride, ?RideStatus $from, ActorType $actorType, ?int $actorId, ?array $meta = null): void
    {
        RideEvent::create([
            'ride_id' => $ride->id,
            'from_status' => $from,
            'to_status' => $ride->status,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'meta' => $meta,
        ]);
    }
}
