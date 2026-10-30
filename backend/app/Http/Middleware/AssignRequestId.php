<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id and adds it to all log lines and to the
 * response. When a user reports a problem, support can search the logs
 * for that one id and see the whole story.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep the id from an upstream proxy if it looks sane, otherwise make one.
        $incoming = (string) $request->header('X-Request-Id');
        $requestId = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
