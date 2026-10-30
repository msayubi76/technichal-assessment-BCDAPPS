<?php

namespace App\Http\Resources;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
class RideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User|null $viewer */
        $viewer = $request->user();
        $viewerIsAssignedDriver = $viewer?->isDriver() && $this->driver_id !== null
            && $this->driver_id === $viewer->driver?->id;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'category' => $this->category->value,
            'pickup' => ['name' => $this->pickup_name, 'lat' => $this->pickup_lat, 'lng' => $this->pickup_lng],
            'dropoff' => ['name' => $this->dropoff_name, 'lat' => $this->dropoff_lat, 'lng' => $this->dropoff_lng],
            'distance_meters' => $this->distance_meters,
            'duration_seconds' => $this->duration_seconds,
            'pickup_eta_seconds' => $this->pickup_eta_seconds,
            'distance_to_pickup_meters' => $this->whenHas('distance_to_pickup_meters', fn ($meters) => (int) round($meters)),
            'fare' => [
                'currency' => $this->currency,
                'base' => $this->base_fare,
                'distance' => $this->distance_fare,
                'time' => $this->time_fare,
                'surge_multiplier' => (float) $this->surge_multiplier,
                'total' => $this->total_fare,
                // Drivers see what they will earn. Riders only see what they pay.
                'driver_earning' => $this->when($viewer?->isDriver() ?? false, $this->driver_earning),
            ],
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? new DriverResource($this->driver) : null),
            'rider' => $this->whenLoaded('rider', fn () => ['name' => $this->rider->name]),
            'cancelled_by' => $this->cancelled_by?->value,
            'cancellation_reason' => $this->cancellation_reason,
            'can_cancel' => $this->status->isActive()
                && ($viewer?->id === $this->rider_id || ($viewerIsAssignedDriver && $this->status === RideStatus::Accepted)),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
