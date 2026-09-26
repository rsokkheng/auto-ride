<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Makes a write safe to retry: a request carrying an `Idempotency-Key` header
 * runs once, and any repeat with the same key (a network retry, a double tap)
 * gets the first response replayed instead of creating a second ride, top-up,
 * withdrawal or order.
 *
 * - No header → passes straight through (fully backward compatible).
 * - A duplicate arriving while the first is still running waits for it, then
 *   receives the same response.
 * - Same key with a different body → 422 (a client bug, never silently merged).
 * - 5xx responses are not stored, so a genuine server failure can be retried.
 *
 * Keys are scoped to the caller's bearer token + method + path.
 */
class EnsureIdempotency
{
    /** How long a response stays replayable. */
    private const TTL_SECONDS = 86400;

    /** Upper bound on how long one request may hold the key. */
    private const LOCK_SECONDS = 60;

    /** How long a concurrent duplicate waits for the first to finish. */
    private const WAIT_SECONDS = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '' || $request->isMethodSafe()) {
            return $next($request);
        }

        if (strlen($key) > 100) {
            return response()->json(['message' => 'Invalid Idempotency-Key.'], 422);
        }

        $scope = 'idempotency:' . sha1(implode('|', [
            $request->bearerToken() ?? $request->ip(),
            $request->method(),
            $request->path(),
            $key,
        ]));
        $fingerprint = sha1($request->getContent());

        $lock = Cache::lock($scope . ':lock', self::LOCK_SECONDS);

        try {
            $lock->block(self::WAIT_SECONDS);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'This request is still being processed. Please wait.'], 409);
        }

        try {
            if ($stored = Cache::get($scope)) {
                if ($stored['fingerprint'] !== $fingerprint) {
                    return response()->json(['message' => 'Idempotency-Key was already used for a different request.'], 422);
                }

                return response($stored['body'], $stored['status'], [
                    'Content-Type'        => $stored['content_type'],
                    'Idempotent-Replayed' => 'true',
                ]);
            }

            $response = $next($request);

            if ($response->getStatusCode() < 500
                && ! $response instanceof StreamedResponse
                && ! $response instanceof BinaryFileResponse) {
                Cache::put($scope, [
                    'fingerprint'  => $fingerprint,
                    'status'       => $response->getStatusCode(),
                    'content_type' => $response->headers->get('Content-Type', 'application/json'),
                    'body'         => $response->getContent(),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
