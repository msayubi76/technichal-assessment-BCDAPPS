<?php

namespace App\Http\Resources;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 */
class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'category' => $this->category->value,
            'vehicle_model' => $this->vehicle_model,
            'vehicle_color' => $this->vehicle_color,
            'plate_number' => $this->plate_number,
            'status' => $this->status->value,
            'rating' => (float) $this->rating,
            'location' => $this->current_lat === null ? null : [
                'lat' => $this->current_lat,
                'lng' => $this->current_lng,
            ],
        ];
    }
}
