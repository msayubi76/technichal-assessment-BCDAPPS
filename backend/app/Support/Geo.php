<?php

namespace App\Support;

class Geo
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * Straight-line distance between two points on the earth (haversine formula).
     */
    public static function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): int
    {
        $dLat = deg2rad($toLat - $fromLat);
        $dLng = deg2rad($toLng - $fromLng);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($dLng / 2) ** 2;

        return (int) round(2 * self::EARTH_RADIUS_METERS * asin(sqrt($a)));
    }
}
