<?php

namespace App\Jobs;

use App\Services\FirestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one FirestoreService call off the request cycle. Firestore writes are
 * an HTTP round-trip to Google — on a hot path like a GPS tick, dispatching
 * this instead of calling the service directly keeps the API response from
 * blocking on Firestore's latency.
 *
 * Best-effort like the synchronous calls it replaces: failures are logged,
 * never surfaced to the driver/passenger who triggered the sync.
 */
class SyncFirestore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    /**
     * @param string $method One of FirestoreService's public sync-/update-prefixed methods.
     * @param array  $args   Positional arguments for that method (models included — serialized safely).
     */
    public function __construct(
        private string $method,
        private array $args,
    ) {}

    public function handle(FirestoreService $firestore): void
    {
        $firestore->{$this->method}(...$this->args);
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncFirestore job failed permanently', [
            'method' => $this->method,
            'error'  => $e->getMessage(),
        ]);
    }
}
