<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets the request through if the signed-in user has the given role.
 * Usage in routes: ->middleware('role:driver')
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role?->value !== $role) {
            throw new ApiException('forbidden', "Only {$role}s can do this.", 403);
        }

        return $next($request);
    }
}
