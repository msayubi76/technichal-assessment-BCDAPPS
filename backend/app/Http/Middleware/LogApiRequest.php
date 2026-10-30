<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one short log line per API call: who, what, result and how long it took.
 * Request bodies are not logged, so passwords and personal data stay out of the logs.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        // Docker checks health every few seconds, which would bury the useful lines.
        if ($request->is('api/health') && $response->isSuccessful()) {
            return $response;
        }

        $status = $response->getStatusCode();
        $level = match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };

        Log::log($level, 'api.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'user_id' => $request->user()?->id,
        ]);

        return $response;
    }
}
