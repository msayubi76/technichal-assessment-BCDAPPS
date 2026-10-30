<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelRideRequest;
use App\Http\Requests\CreateRideRequest;
use App\Http\Requests\EstimateRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Services\Rides\RideService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(private readonly RideService $rides) {}

    public function estimate(EstimateRideRequest $request): JsonResponse
    {
        return ApiResponse::success($this->rides->estimate($request->pickup(), $request->dropoff()));
    }

    public function store(CreateRideRequest $request): JsonResponse
    {
        $ride = $this->rides->request(
            $request->user(),
            $request->category(),
            $request->pickup(),
            $request->dropoff(),
        );

        return ApiResponse::created(new RideResource($ride));
    }

    public function index(Request $request): JsonResponse
    {
        $rides = Ride::visibleTo($request->user())
            ->with('driver.user')
            ->latest('requested_at')
            ->limit(20)
            ->get();

        return ApiResponse::success(RideResource::collection($rides));
    }

    /**
     * The rider's ride in progress, if any. The app calls this on load so a
     * page refresh brings the rider straight back to their ride.
     */
    public function current(Request $request): JsonResponse
    {
        $ride = Ride::visibleTo($request->user())->active()->with('driver.user')->first();

        return ApiResponse::success($ride ? new RideResource($ride) : null);
    }

    public function show(Request $request, string $ride): JsonResponse
    {
        // Someone else's ride gives the same 404 as a ride that doesn't exist.
        $ride = Ride::visibleTo($request->user())->with(['driver.user', 'rider'])->findOrFail($ride);

        return ApiResponse::success(new RideResource($ride));
    }

    public function cancel(CancelRideRequest $request, string $ride): JsonResponse
    {
        $ride = $this->rides->cancel($ride, $request->user(), $request->validated('reason'));

        return ApiResponse::success(new RideResource($ride->load('driver.user')));
    }
}
