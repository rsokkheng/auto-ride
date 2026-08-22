<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Detects a dead/stopped queue worker before it silently stalls ride dispatch,
 * self-serve expiry, and promo pushes for hours (as happened once already —
 * a worker died and rides sat mid-dispatch with no job left to advance them).
 *
 * Logs 'queue_worker_appears_stuck' at critical level when the oldest pending
 * job is older than its available_at by more than the stale threshold —
 * wire this into whatever log-based alerting/monitoring is available.
 */
#[Signature('queue:health-check')]
#[Description('Warns if the queue appears stuck (oldest pending job overdue by more than the stale threshold).')]
class QueueHealthCheck extends Command
{
    private const STALE_MINUTES = 5;

    public function handle(): void
    {
        $oldest = DB::table('jobs')->orderBy('available_at')->first();

        if (! $oldest) {
            $this->info('Queue healthy — no pending jobs.');
            return;
        }

        $overdueMinutes = round(Carbon::createFromTimestamp($oldest->available_at)->diffInMinutes(now(), true), 1);

        if ($overdueMinutes < self::STALE_MINUTES) {
            $this->info("Queue healthy — oldest pending job is {$overdueMinutes} min past due.");
            return;
        }

        $payload = json_decode($oldest->payload, true);

        Log::critical('queue_worker_appears_stuck', [
            'oldest_job_id'    => $oldest->id,
            'job_class'        => $payload['displayName'] ?? 'unknown',
            'overdue_minutes'  => $overdueMinutes,
            'pending_count'    => DB::table('jobs')->count(),
        ]);

        $this->error("Queue appears stuck — oldest job (#{$oldest->id}) is {$overdueMinutes} min overdue. Is queue:work running?");
    }
}
