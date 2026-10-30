<?php

use App\Enums\RideStatus;
use App\Exceptions\ApiException;
use App\Models\Ride;
use App\Services\Rides\RideStateMachine;

it('allows only the defined status changes', function (RideStatus $from, RideStatus $to, bool $allowed) {
    expect((new RideStateMachine)->canMove($from, $to))->toBe($allowed);
})->with([
    'requested to accepted' => [RideStatus::Requested, RideStatus::Accepted, true],
    'requested to cancelled' => [RideStatus::Requested, RideStatus::Cancelled, true],
    'accepted to completed' => [RideStatus::Accepted, RideStatus::Completed, true],
    'accepted to cancelled' => [RideStatus::Accepted, RideStatus::Cancelled, true],
    'requested to completed' => [RideStatus::Requested, RideStatus::Completed, false],
    'accepted to accepted' => [RideStatus::Accepted, RideStatus::Accepted, false],
    'completed to cancelled' => [RideStatus::Completed, RideStatus::Cancelled, false],
    'cancelled to accepted' => [RideStatus::Cancelled, RideStatus::Accepted, false],
    'completed to accepted' => [RideStatus::Completed, RideStatus::Accepted, false],
]);

it('explains why a change is refused', function () {
    $ride = new Ride(['status' => RideStatus::Completed]);

    try {
        (new RideStateMachine)->assertCanMove($ride, RideStatus::Cancelled);
        $this->fail('Expected the change to be refused.');
    } catch (ApiException $e) {
        expect($e->errorCode)->toBe('invalid_transition')
            ->and($e->status)->toBe(409)
            ->and($e->details)->toBe(['from' => 'completed', 'to' => 'cancelled']);
    }
});
