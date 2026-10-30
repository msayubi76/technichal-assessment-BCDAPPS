<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Tells one nearby driver that a new ride is waiting to be accepted.
 */
class RideRequested implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $rideId,
        public readonly int $driverId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("drivers.{$this->driverId}")];
    }

    public function broadcastAs(): string
    {
        return 'ride.requested';
    }

    public function broadcastWith(): array
    {
        return ['ride_id' => $this->rideId];
    }
}
