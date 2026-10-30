<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'rider_id', 'city_id', 'driver_id', 'category', 'status',
    'pickup_name', 'pickup_lat', 'pickup_lng', 'dropoff_name', 'dropoff_lat', 'dropoff_lng',
    'distance_meters', 'duration_seconds', 'pickup_eta_seconds',
    'currency', 'base_fare', 'distance_fare', 'time_fare', 'surge_multiplier',
    'total_fare', 'commission_amount', 'driver_earning',
    'requested_at', 'accepted_at', 'completed_at', 'cancelled_at',
    'cancelled_by', 'cancellation_reason',
])]
class Ride extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'category' => RideCategory::class,
            'status' => RideStatus::class,
            'cancelled_by' => ActorType::class,
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'distance_meters' => 'integer',
            'duration_seconds' => 'integer',
            'pickup_eta_seconds' => 'integer',
            'base_fare' => 'integer',
            'distance_fare' => 'integer',
            'time_fare' => 'integer',
            'surge_multiplier' => 'decimal:2',
            'total_fare' => 'integer',
            'commission_amount' => 'integer',
            'driver_earning' => 'integer',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(RideAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RideEvent::class)->orderBy('id');
    }

    /**
     * Rides this user is allowed to see: a rider sees their own rides,
     * a driver sees the rides assigned to them.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isRider()) {
            $query->where('rider_id', $user->id);

            return;
        }

        $query->where('driver_id', $user->driver?->id ?? 0);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', RideStatus::activeValues());
    }
}
