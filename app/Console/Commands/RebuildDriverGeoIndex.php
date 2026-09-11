<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DriverGeoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('drivers:rebuild-geo-index')]
#[Description('Rebuild the Redis GEO driver index from MySQL — run after a Redis flush/restart or before first enabling Redis-backed matching.')]
class RebuildDriverGeoIndex extends Command
{
    public function handle(DriverGeoService $geo): void
    {
        $count = 0;

        User::where('role', 'driver')
            ->where('available', true)
            ->where(fn ($q) => $q->whereNull('penalty_until')->orWhere('penalty_until', '<=', now()))
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->select('id', 'current_latitude', 'current_longitude')
            ->chunkById(500, function ($drivers) use ($geo, &$count) {
                foreach ($drivers as $driver) {
                    $geo->add($driver->id, (float) $driver->current_latitude, (float) $driver->current_longitude);
                    $count++;
                }
            });

        $this->info("Rebuilt Redis GEO index with {$count} available driver(s).");
    }
}
