<?php

namespace App\Services\Rides;

use App\Models\FareRule;
use App\Support\Geo;

/**
 * Works out the price of a trip from the city's fare rules.
 *
 * fare = base + (per km x km) + (per minute x minutes)
 * then the minimum fare is applied and the total is rounded up to a whole rupee.
 * The platform keeps its commission and the rest goes to the driver.
 */
class FareCalculator
{
    /**
     * @param  array{lat: float, lng: float}  $pickup
     * @param  array{lat: float, lng: float}  $dropoff
     */
    public function quote(FareRule $rule, array $pickup, array $dropoff, string $currency): FareQuote
    {
        $distanceMeters = $this->roadDistanceMeters($pickup, $dropoff);
        $durationSeconds = $this->travelSeconds($distanceMeters, $rule->average_speed_kmh);

        $baseFare = $rule->base_fare;
        $distanceFare = (int) round($rule->per_km_rate * $distanceMeters / 1000);
        $timeFare = (int) round($rule->per_minute_rate * $durationSeconds / 60);

        // Surge pricing would raise this in busy areas. It is fixed at 1 for now.
        $surgeMultiplier = 1.0;

        $total = (int) round(($baseFare + $distanceFare + $timeFare) * $surgeMultiplier);
        $total = max($total, $rule->minimum_fare);
        $total = (int) (ceil($total / 100) * 100);

        $commission = (int) round($total * (float) $rule->commission_percent / 100);

        return new FareQuote(
            currency: $currency,
            distanceMeters: $distanceMeters,
            durationSeconds: $durationSeconds,
            baseFare: $baseFare,
            distanceFare: $distanceFare,
            timeFare: $timeFare,
            surgeMultiplier: $surgeMultiplier,
            totalFare: $total,
            commissionAmount: $commission,
            driverEarning: $total - $commission,
        );
    }

    /**
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     */
    public function roadDistanceMeters(array $from, array $to): int
    {
        $straight = Geo::distanceMeters($from['lat'], $from['lng'], $to['lat'], $to['lng']);

        return (int) round($straight * config('rides.road_distance_factor'));
    }

    /**
     * How long it takes to drive a distance at the city's average speed.
     */
    public function travelSeconds(int $meters, int $averageSpeedKmh): int
    {
        return (int) round($meters / ($averageSpeedKmh * 1000 / 3600));
    }
}
