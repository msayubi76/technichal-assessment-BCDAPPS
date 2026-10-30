<?php

namespace App\Services\Rides;

/**
 * The price of a trip with every part shown. Amounts are in the smallest
 * currency unit (paisa for PKR).
 */
final readonly class FareQuote
{
    public function __construct(
        public string $currency,
        public int $distanceMeters,
        public int $durationSeconds,
        public int $baseFare,
        public int $distanceFare,
        public int $timeFare,
        public float $surgeMultiplier,
        public int $totalFare,
        public int $commissionAmount,
        public int $driverEarning,
    ) {}

    /**
     * @return array<string, int|float|string>
     */
    public function toRideAttributes(): array
    {
        return [
            'currency' => $this->currency,
            'distance_meters' => $this->distanceMeters,
            'duration_seconds' => $this->durationSeconds,
            'base_fare' => $this->baseFare,
            'distance_fare' => $this->distanceFare,
            'time_fare' => $this->timeFare,
            'surge_multiplier' => $this->surgeMultiplier,
            'total_fare' => $this->totalFare,
            'commission_amount' => $this->commissionAmount,
            'driver_earning' => $this->driverEarning,
        ];
    }
}
