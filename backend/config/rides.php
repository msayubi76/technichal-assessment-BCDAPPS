<?php

return [

    // How far from the pickup point we look for available drivers.
    'search_radius_meters' => (int) env('RIDES_SEARCH_RADIUS_METERS', 6000),

    // How many of the nearest drivers get told about a new ride request.
    'notify_driver_count' => (int) env('RIDES_NOTIFY_DRIVER_COUNT', 5),

    // Roads are never a straight line. Multiplying the straight-line distance
    // by this gives a fair guess of the real driving distance.
    'road_distance_factor' => 1.3,

    // Rides shorter than this are almost always a mistake (same place picked twice).
    'minimum_trip_meters' => 300,

    // How long a saved Idempotency-Key response is kept for replays.
    'idempotency_ttl_hours' => 24,

];
