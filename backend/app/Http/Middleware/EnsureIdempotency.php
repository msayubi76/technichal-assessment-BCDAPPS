<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes write requests safe to retry.
 *
 * The app sends a unique Idempotency-Key header with each action (one key
 * per tap on "Request ride", for example). If the same request arrives again
 * with the same key, because of a bad network or a double tap, we send back
 * the saved response instead of doing the work a second time.
 *
 * - Same key, same request, finished  -> replay the saved response
 * - Same key, same request, still running -> 409, try again shortly
 * - Same key, different request       -> 422, the key was reused by mistake
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key');

        if (! preg_match('/^[A-Za-z0-9\-_]{8,100}$/', $key)) {
            throw new ApiException(
                'idempotency_key_required',
                'Send a unique Idempotency-Key header (8 to 100 letters, numbers or dashes) with this request.',
                400,
            );
        }

        $userId = $request->user()->id;
        $requestHash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        $record = $this->claim($userId, $key, $request, $requestHash);

        if ($record->wasRecentlyCreated === false) {
            return $this->replay($record, $requestHash);
        }

        $response = $next($request);

        // Server errors are not saved, so the app can retry with the same key
        // once the problem is fixed. Everything else, including business rule
        // errors, is saved and replayed as-is.
        if ($response->getStatusCode() >= 500) {
            $record->delete();
        } else {
            $record->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        }

        return $response;
    }

    /**
     * Saves the key before doing any work. The unique index on (user, key)
     * means only one of two identical requests can do this; the other one
     * gets the existing row back.
     */
    private function claim(int $userId, string $key, Request $request, string $requestHash): IdempotencyKey
    {
        $existing = IdempotencyKey::where('user_id', $userId)->where('key', $key)->first();

        if ($existing !== null && $existing->expires_at->isPast()) {
            $existing->delete();
            $existing = null;
        }

        if ($existing !== null) {
            return $existing;
        }

        try {
            return IdempotencyKey::create([
                'user_id' => $userId,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'request_hash' => $requestHash,
                'expires_at' => now()->addHours(config('rides.idempotency_ttl_hours')),
            ]);
        } catch (UniqueConstraintViolationException) {
            return IdempotencyKey::where('user_id', $userId)->where('key', $key)->firstOrFail();
        }
    }

    private function replay(IdempotencyKey $record, string $requestHash): Response
    {
        if (! hash_equals($record->request_hash, $requestHash)) {
            throw new ApiException(
                'idempotency_key_reused',
                'This Idempotency-Key was already used for a different request.',
                422,
            );
        }

        if (! $record->isFinished()) {
            throw new ApiException(
                'request_in_progress',
                'This request is still being processed. Please try again in a moment.',
                409,
            );
        }

        return response($record->response_body, $record->response_status, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
