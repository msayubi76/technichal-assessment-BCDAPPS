<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstimateRideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pickup' => ['required', 'array'],
            'pickup.name' => ['required', 'string', 'max:255'],
            'pickup.lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff' => ['required', 'array'],
            'dropoff.name' => ['required', 'string', 'max:255'],
            'dropoff.lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function attributes(): array
    {
        return [
            'pickup.name' => 'pickup location',
            'pickup.lat' => 'pickup latitude',
            'pickup.lng' => 'pickup longitude',
            'dropoff.name' => 'destination',
            'dropoff.lat' => 'destination latitude',
            'dropoff.lng' => 'destination longitude',
        ];
    }

    /**
     * @return array{name: string, lat: float, lng: float}
     */
    public function pickup(): array
    {
        return $this->location('pickup');
    }

    /**
     * @return array{name: string, lat: float, lng: float}
     */
    public function dropoff(): array
    {
        return $this->location('dropoff');
    }

    /**
     * @return array{name: string, lat: float, lng: float}
     */
    private function location(string $key): array
    {
        $location = $this->validated($key);

        return [
            'name' => trim($location['name']),
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
        ];
    }
}
