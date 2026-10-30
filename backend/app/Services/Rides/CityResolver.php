<?php

namespace App\Services\Rides;

use App\Exceptions\ApiException;
use App\Models\City;

/**
 * Finds which of our cities a trip belongs to.
 */
class CityResolver
{
    /**
     * @param  array{lat: float, lng: float}  $pickup
     * @param  array{lat: float, lng: float}  $dropoff
     */
    public function forTrip(array $pickup, array $dropoff): City
    {
        $city = City::active()->get()
            ->first(fn (City $city) => $city->covers($pickup['lat'], $pickup['lng']));

        if ($city === null) {
            throw new ApiException('outside_service_area', "Sorry, we don't operate at this pickup location yet.");
        }

        if (! $city->covers($dropoff['lat'], $dropoff['lng'])) {
            throw new ApiException('outside_service_area', "The destination must be inside {$city->name}.");
        }

        return $city;
    }
}
