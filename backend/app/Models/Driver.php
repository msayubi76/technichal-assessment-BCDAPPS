<?php

namespace App\Models;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'city_id', 'category', 'vehicle_model', 'vehicle_color', 'plate_number',
    'status', 'current_lat', 'current_lng', 'location_updated_at', 'rating',
])]
class Driver extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => RideCategory::class,
            'status' => DriverStatus::class,
            'current_lat' => 'float',
            'current_lng' => 'float',
            'location_updated_at' => 'datetime',
            'rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === DriverStatus::Available;
    }
}
