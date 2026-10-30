<?php

namespace App\Services\Rides;

use App\Enums\RideStatus;
use App\Exceptions\ApiException;
use App\Models\Ride;

/**
 * The only allowed ways a ride can move between statuses:
 *
 *   requested -> accepted -> completed
 *   requested -> cancelled
 *   accepted  -> cancelled
 *
 * Completed and cancelled are final.
 */
class RideStateMachine
{
    /**
     * @return list<RideStatus>
     */
    public function nextStatuses(RideStatus $from): array
    {
        return match ($from) {
            RideStatus::Requested => [RideStatus::Accepted, RideStatus::Cancelled],
            RideStatus::Accepted => [RideStatus::Completed, RideStatus::Cancelled],
            RideStatus::Completed, RideStatus::Cancelled => [],
        };
    }

    public function canMove(RideStatus $from, RideStatus $to): bool
    {
        return in_array($to, $this->nextStatuses($from), true);
    }

    public function assertCanMove(Ride $ride, RideStatus $to): void
    {
        if ($this->canMove($ride->status, $to)) {
            return;
        }

        $message = match ($ride->status) {
            RideStatus::Requested => 'This ride has not been accepted by a driver yet.',
            RideStatus::Accepted => 'This ride has already been accepted by a driver.',
            RideStatus::Completed => 'This ride is already completed.',
            RideStatus::Cancelled => 'This ride was cancelled.',
        };

        throw new ApiException('invalid_transition', $message, 409, [
            'from' => $ride->status->value,
            'to' => $to->value,
        ]);
    }
}
