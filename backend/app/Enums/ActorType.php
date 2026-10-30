<?php

namespace App\Enums;

/**
 * Who caused a change to a ride.
 */
enum ActorType: string
{
    case Rider = 'rider';
    case Driver = 'driver';
    case System = 'system';
}
