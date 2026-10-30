<?php

namespace App\Services\Rides;

use App\Events\RideRequested;
use App\Events\RideStatusChanged;
use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends live updates over WebSockets.
 *
 * Only called after the database change is saved. If the WebSocket server
 * is down we log it and carry on: the ride is already saved, and the app
 * falls back to checking the API every few seconds.
 */
class RideNotifier
{
    public function statusChanged(Ride $ride): void
    {
        $this->send(new RideStatusChanged($ride->id, $ride->status->value));
    }

    /**
     * @param  iterable<Driver>  $drivers
     */
    public function offerToDrivers(Ride $ride, iterable $drivers): void
    {
        foreach ($drivers as $driver) {
            $this->send(new RideRequested($ride->id, $driver->id));
        }
    }

    private function send(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('broadcast.failed', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
