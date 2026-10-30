<?php

namespace App\Http\Resources;

use App\Models\City;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'currency' => $this->currency,
            'center' => ['lat' => $this->center_lat, 'lng' => $this->center_lng],
            'places' => $this->whenLoaded('places', fn () => $this->places->map(fn (Place $place) => [
                'id' => $place->id,
                'name' => $place->name,
                'area' => $place->area,
                'lat' => $place->lat,
                'lng' => $place->lng,
            ])),
        ];
    }
}
