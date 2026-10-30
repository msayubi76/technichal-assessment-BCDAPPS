<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ride_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'meta', 'created_at'])]
class RideEvent extends Model
{
    // Events are written once and never changed, so only created_at is kept.
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => RideStatus::class,
            'to_status' => RideStatus::class,
            'actor_type' => ActorType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
