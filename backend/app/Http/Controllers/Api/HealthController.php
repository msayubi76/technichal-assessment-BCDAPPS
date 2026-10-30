<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Used by Docker and load balancers to check the app can serve traffic.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            Log::error('health.database_unreachable', ['error' => $e->getMessage()]);

            return ApiResponse::error('unhealthy', 'Database is not reachable.', 503);
        }

        return ApiResponse::success(['status' => 'ok', 'database' => 'ok']);
    }
}
