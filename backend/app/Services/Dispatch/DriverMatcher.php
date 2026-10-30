<?php

namespace App\Services\Dispatch;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds drivers near a rider, and open ride requests near a driver.
 *
 * Distances are worked out by MySQL (ST_Distance_Sphere). A rectangle filter
 * runs first so the database only measures drivers that are roughly close.
 * At larger scale this would move to a geo index such as Redis GEO or H3 cells.
 */
class DriverMatcher
{
    /**
     * Available drivers of the right category in the same city, closest first.
     * Each driver gets a "distance_meters" value from the query.
     *
     * @return Collection<int, Driver>
     */
    public function nearestAvailable(int $cityId, RideCategory $category, float $lat, float $lng, int $limit): Collection
    {
        $radius = config('rides.search_radius_meters');
        [$minLat, $maxLat, $minLng, $maxLng] = $this->boundingBox($lat, $lng, $radius);

        return Driver::query()
            ->select('drivers.*')
            ->selectRaw('ST_Distance_Sphere(POINT(current_lng, current_lat), POINT(?, ?)) AS distance_meters', [$lng, $lat])
            ->where('city_id', $cityId)
            ->where('status', DriverStatus::Available)
            ->where('category', $category)
            ->whereBetween('current_lat', [$minLat, $maxLat])
            ->whereBetween('current_lng', [$minLng, $maxLng])
            ->having('distance_meters', '<=', $radius)
            ->orderBy('distance_meters')
            ->limit($limit)
            ->get();
    }

    /**
     * Ride requests waiting for a driver near this driver, closest pickup first.
     * Each ride gets a "distance_to_pickup_meters" value from the query.
     *
     * @return Collection<int, Ride>
     */
    public function openRidesNear(Driver $driver, int $limit = 20): Collection
    {
        if (! $driver->isAvailable() || $driver->current_lat === null) {
            return new Collection;
        }

        $radius = config('rides.search_radius_meters');
        [$minLat, $maxLat, $minLng, $maxLng] = $this->boundingBox($driver->current_lat, $driver->current_lng, $radius);

        return Ride::query()
            ->select('rides.*')
            ->selectRaw(
                'ST_Distance_Sphere(POINT(pickup_lng, pickup_lat), POINT(?, ?)) AS distance_to_pickup_meters',
                [$driver->current_lng, $driver->current_lat],
            )
            ->where('city_id', $driver->city_id)
            ->where('status', RideStatus::Requested)
            ->where('category', $driver->category)
            ->whereBetween('pickup_lat', [$minLat, $maxLat])
            ->whereBetween('pickup_lng', [$minLng, $maxLng])
            ->having('distance_to_pickup_meters', '<=', $radius)
            ->orderBy('distance_to_pickup_meters')
            ->limit($limit)
            ->with('rider')
            ->get();
    }

    /**
     * A rectangle around a point, roughly $radius meters in every direction.
     *
     * @return array{float, float, float, float}
     */
    private function boundingBox(float $lat, float $lng, int $radius): array
    {
        $metersPerDegree = 111_320;
        $latDelta = $radius / $metersPerDegree;
        $lngDelta = $radius / ($metersPerDegree * cos(deg2rad($lat)));

        return [$lat - $latDelta, $lat + $latDelta, $lng - $lngDelta, $lng + $lngDelta];
    }
}
