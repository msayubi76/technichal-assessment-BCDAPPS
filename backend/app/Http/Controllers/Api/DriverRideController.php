<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDriverLocationRequest;
use App\Http\Requests\UpdateDriverStatusRequest;
use App\Http\Resources\DriverResource;
use App\Http\Resources\RideResource;
use App\Models\Driver;
use App\Models\Ride;
use App\Services\Dispatch\DriverAvailabilityService;
use App\Services\Dispatch\DriverMatcher;
use App\Services\Rides\RideService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverRideController extends Controller
{
    public function __construct(
        private readonly RideService $rides,
        private readonly DriverMatcher $matcher,
        private readonly DriverAvailabilityService $availability,
    ) {}

    /**
     * Ride requests waiting nearby that this driver could accept.
     */
    public function available(Request $request): JsonResponse
    {
        $rides = $this->matcher->openRidesNear($this->driver($request));

        return ApiResponse::success(RideResource::collection($rides));
    }

    public function current(Request $request): JsonResponse
    {
        $ride = Ride::where('driver_id', $this->driver($request)->id)
            ->active()
            ->with(['driver.user', 'rider'])
            ->first();

        return ApiResponse::success($ride ? new RideResource($ride) : null);
    }

    public function accept(Request $request, string $ride): JsonResponse
    {
        $ride = $this->rides->accept($ride, $this->driver($request));

        return ApiResponse::success(new RideResource($ride->load(['driver.user', 'rider'])));
    }

    public function arrive(Request $request, string $ride): JsonResponse
    {
        $ride = $this->rides->arrive($ride, $this->driver($request));

        return ApiResponse::success(new RideResource($ride->load(['driver.user', 'rider'])));
    }

    public function start(Request $request, string $ride): JsonResponse
    {
        $ride = $this->rides->start($ride, $this->driver($request));

        return ApiResponse::success(new RideResource($ride->load(['driver.user', 'rider'])));
    }

    public function complete(Request $request, string $ride): JsonResponse
    {
        $ride = $this->rides->complete($ride, $this->driver($request));

        return ApiResponse::success(new RideResource($ride->load(['driver.user', 'rider'])));
    }

    public function updateStatus(UpdateDriverStatusRequest $request): JsonResponse
    {
        $driver = $this->availability->setStatus($this->driver($request), $request->status());

        return ApiResponse::success(new DriverResource($driver->load('user')));
    }

    public function updateLocation(UpdateDriverLocationRequest $request): JsonResponse
    {
        $driver = $this->availability->moveTo(
            $this->driver($request),
            (float) $request->validated('lat'),
            (float) $request->validated('lng'),
        );

        return ApiResponse::success(new DriverResource($driver->load('user')));
    }

    private function driver(Request $request): Driver
    {
        return $request->user()->driver()->firstOrFail();
    }
}
