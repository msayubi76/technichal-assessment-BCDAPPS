<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'country_code', 'currency', 'timezone',
    'center_lat', 'center_lng', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'is_active',
])]
class City extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'center_lat' => 'float',
            'center_lng' => 'float',
            'min_lat' => 'float',
            'max_lat' => 'float',
            'min_lng' => 'float',
            'max_lng' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function fareRules(): HasMany
    {
        return $this->hasMany(FareRule::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function covers(float $lat, float $lng): bool
    {
        return $lat >= $this->min_lat && $lat <= $this->max_lat
            && $lng >= $this->min_lng && $lng <= $this->max_lng;
    }
}
