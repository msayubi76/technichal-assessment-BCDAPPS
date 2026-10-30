<?php

use App\Enums\RideCategory;
use App\Models\FareRule;
use App\Services\Rides\FareCalculator;

// Lahore Economy prices, in paisa.
function economyRule(): FareRule
{
    return new FareRule([
        'category' => RideCategory::Economy,
        'base_fare' => 10000,
        'per_km_rate' => 3500,
        'per_minute_rate' => 500,
        'minimum_fare' => 20000,
        'commission_percent' => 15,
        'average_speed_kmh' => 22,
    ]);
}

// Two points exactly 10 km apart in a straight line (same longitude).
$start = ['lat' => 31.40, 'lng' => 74.30];
$tenKmNorth = ['lat' => 31.40 + 0.08993216, 'lng' => 74.30];

it('prices a trip from distance and time', function () use ($start, $tenKmNorth) {
    $quote = (new FareCalculator)->quote(economyRule(), $start, $tenKmNorth, 'PKR');

    // 10 km straight line x 1.3 road factor = 13 km of driving.
    expect($quote->distanceMeters)->toBe(13000)
        // 13 km at 22 km/h
        ->and($quote->durationSeconds)->toBe(2127)
        ->and($quote->baseFare)->toBe(10000)
        // 13 km x Rs 35
        ->and($quote->distanceFare)->toBe(45500)
        // 2127 s = 35.45 min x Rs 5
        ->and($quote->timeFare)->toBe(17725)
        // 73,225 paisa rounded up to a whole rupee
        ->and($quote->totalFare)->toBe(73300)
        ->and($quote->currency)->toBe('PKR');
});

it('splits the fare between the platform and the driver', function () use ($start, $tenKmNorth) {
    $quote = (new FareCalculator)->quote(economyRule(), $start, $tenKmNorth, 'PKR');

    expect($quote->commissionAmount)->toBe(10995)
        ->and($quote->driverEarning)->toBe(62305)
        ->and($quote->commissionAmount + $quote->driverEarning)->toBe($quote->totalFare);
});

it('charges the minimum fare for a very short trip', function () use ($start) {
    $oneKmNorth = ['lat' => 31.40 + 0.0089932, 'lng' => 74.30];

    $quote = (new FareCalculator)->quote(economyRule(), $start, $oneKmNorth, 'PKR');

    expect($quote->totalFare)->toBe(20000);
});

it('always ends on a whole rupee', function () use ($start) {
    $somewhere = ['lat' => 31.4567, 'lng' => 74.3891];

    $quote = (new FareCalculator)->quote(economyRule(), $start, $somewhere, 'PKR');

    expect($quote->totalFare % 100)->toBe(0);
});
