<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns any exception thrown during an API request into our standard
 * error response, so the app never has to parse an HTML error page.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e): Response
    {
        return match (true) {
            // Code that already built its own response wants it sent as-is.
            $e instanceof HttpResponseException => $e->getResponse(),

            $e instanceof ApiException => ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->details),

            $e instanceof ValidationException => ApiResponse::error(
                'validation_failed',
                'Some of the information sent is missing or invalid.',
                422,
                $e->errors(),
            ),

            $e instanceof AuthenticationException => ApiResponse::error('unauthenticated', 'Please sign in to continue.', 401),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::error('forbidden', 'You are not allowed to do this.', 403),

            // Same answer for "doesn't exist" and "not yours", so nobody can
            // find out which ride ids are real by guessing.
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error('not_found', 'We could not find what you were looking for.', 404),

            $e instanceof MethodNotAllowedHttpException => ApiResponse::error('method_not_allowed', 'This action is not supported here.', 405),

            $e instanceof ThrottleRequestsException => ApiResponse::error('too_many_requests', 'Too many requests. Please wait a moment and try again.', 429),

            $e instanceof HttpExceptionInterface => ApiResponse::error('http_error', $e->getMessage() ?: 'The request could not be completed.', $e->getStatusCode()),

            // Anything unexpected. The details go to the log, never to the user.
            default => ApiResponse::error('server_error', 'Something went wrong on our side. Please try again.', 500),
        };
    }
}
