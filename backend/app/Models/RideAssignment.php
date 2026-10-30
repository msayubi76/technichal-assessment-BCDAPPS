<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ride_id', 'driver_id', 'driver_lat', 'driver_lng',
    'distance_to_pickup_meters', 'pickup_eta_seconds', 'accepted_at',
])]
class RideAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'driver_lat' => 'float',
            'driver_lng' => 'float',
            'distance_to_pickup_meters' => 'integer',
            'pickup_eta_seconds' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
