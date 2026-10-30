<?php

namespace App\Enums;

enum DriverStatus: string
{
    case Offline = 'offline';
    case Available = 'available';
    case OnTrip = 'on_trip';
}
