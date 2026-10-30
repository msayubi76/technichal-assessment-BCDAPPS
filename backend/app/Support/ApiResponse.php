<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every API response goes through here so the app always gets the same shape.
 *
 * Success: { "data": ..., "meta": {...} }
 * Error:   { "error": { "code", "message", "details", "request_id" } }
 */
class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        $body = ['data' => $data];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function created(mixed $data = null): JsonResponse
    {
        return self::success($data, 201);
    }

    /**
     * @param  string  $code  A stable, machine-readable reason the app can switch on.
     * @param  array<string, mixed>  $details  Extra context, such as field errors.
     */
    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        $error['request_id'] = request()?->attributes->get('request_id');

        return response()->json(['error' => $error], $status);
    }
}
