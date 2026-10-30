<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Tells the rider and the assigned driver that a ride's status changed.
 * The message only says "something changed"; the app then reloads the ride
 * from the API, so a missed or late message can never show wrong data.
 */
class RideStatusChanged implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $rideId,
        public readonly string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("rides.{$this->rideId}")];
    }

    public function broadcastAs(): string
    {
        return 'ride.status_changed';
    }

    public function broadcastWith(): array
    {
        return ['ride_id' => $this->rideId, 'status' => $this->status];
    }
}
