<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    /**
     * Cities we operate in, with their popular places for the pickup and
     * destination pickers.
     */
    public function index(): JsonResponse
    {
        $cities = City::active()->with(['places' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();

        return ApiResponse::success(CityResource::collection($cities));
    }
}
