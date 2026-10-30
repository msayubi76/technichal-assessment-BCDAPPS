<?php

namespace App\Enums;

enum RideStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * A ride is active from the moment it is requested until it is completed
     * or cancelled.
     */
    public function isActive(): bool
    {
        return in_array($this->value, self::activeValues(), true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Requested->value, self::Accepted->value, self::Arrived->value, self::InProgress->value];
    }
}
