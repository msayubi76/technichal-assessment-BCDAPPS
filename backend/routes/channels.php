<?php

use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Live updates for one ride: only its rider and its assigned driver may listen.
Broadcast::channel('rides.{rideId}', function (User $user, string $rideId) {
    return Ride::whereKey($rideId)->visibleTo($user)->exists();
});

// New ride requests for one driver: only that driver may listen.
Broadcast::channel('drivers.{driverId}', function (User $user, int $driverId) {
    return $user->driver?->id === $driverId;
});
