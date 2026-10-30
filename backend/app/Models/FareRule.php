<?php

namespace App\Models;

use App\Enums\RideCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'city_id', 'category', 'base_fare', 'per_km_rate', 'per_minute_rate',
    'minimum_fare', 'commission_percent', 'average_speed_kmh', 'is_active',
])]
class FareRule extends Model
{
    protected function casts(): array
    {
        return [
            'category' => RideCategory::class,
            'base_fare' => 'integer',
            'per_km_rate' => 'integer',
            'per_minute_rate' => 'integer',
            'minimum_fare' => 'integer',
            'commission_percent' => 'decimal:2',
            'average_speed_kmh' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
