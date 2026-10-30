<?php

namespace App\Enums;

enum RideStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * A ride is active while it is waiting for a driver or on its way.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Requested, self::Accepted], true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Requested->value, self::Accepted->value];
    }
}
