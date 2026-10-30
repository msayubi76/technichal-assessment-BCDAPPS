<?php

namespace App\Services\Rides;

use App\Enums\RideStatus;
use App\Exceptions\ApiException;
use App\Models\Ride;

/**
 * The only allowed ways a ride can move between statuses:
 *
 *   requested -> accepted -> arrived -> in_progress -> completed
 *
 * A ride can also be cancelled while it is requested, accepted or arrived.
 * Once the driver is on the way to the destination it can only be completed.
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
            RideStatus::Accepted => [RideStatus::Arrived, RideStatus::Cancelled],
            RideStatus::Arrived => [RideStatus::InProgress, RideStatus::Cancelled],
            RideStatus::InProgress => [RideStatus::Completed],
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

        // Explain the refusal in words the person can act on.
        $message = match (true) {
            $ride->status === RideStatus::Completed => 'This ride is already completed.',
            $ride->status === RideStatus::Cancelled => 'This ride was cancelled.',
            $to === RideStatus::Accepted => 'This ride has already been accepted by a driver.',
            $ride->status === RideStatus::Requested => 'This ride has not been accepted by a driver yet.',
            $to === RideStatus::Cancelled => 'The trip has already started, so it can no longer be cancelled.',
            $ride->status === RideStatus::InProgress => 'The trip has already started.',
            $ride->status === RideStatus::Accepted => 'The driver has not reached the pickup point yet.',
            $to === RideStatus::Completed => 'The trip has not started yet.',
            default => 'The driver is already at the pickup point.',
        };

        throw new ApiException('invalid_transition', $message, 409, [
            'from' => $ride->status->value,
            'to' => $to->value,
        ]);
    }
}
