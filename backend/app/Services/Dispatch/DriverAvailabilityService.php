<?php

namespace App\Services\Dispatch;

use App\Enums\DriverStatus;
use App\Exceptions\ApiException;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

/**
 * Lets a driver go online or offline and say where they are.
 */
class DriverAvailabilityService
{
    public function setStatus(Driver $driver, DriverStatus $status): Driver
    {
        return DB::transaction(function () use ($driver, $status) {
            $driver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();

            // A driver on a trip finishes or cancels it first. Going offline
            // mid-trip would leave the rider stranded.
            if ($driver->status === DriverStatus::OnTrip) {
                throw new ApiException('driver_on_trip', 'Finish or cancel your current ride first.', 409);
            }

            $driver->update(['status' => $status]);

            return $driver;
        });
    }

    /**
     * Records where the driver is now. A phone app would send this from GPS;
     * the web app lets the driver pick their spot from the list of places.
     */
    public function moveTo(Driver $driver, float $lat, float $lng): Driver
    {
        $driver->update([
            'current_lat' => $lat,
            'current_lng' => $lng,
            'location_updated_at' => now(),
        ]);

        return $driver;
    }
}
