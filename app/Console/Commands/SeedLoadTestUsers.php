<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Bulk-seeds driver + passenger accounts for k6 load testing at the
 * 10,000-concurrent-user scale, and exports their api_tokens to a JSON
 * file the k6 script reads directly (no per-VU login calls needed).
 *
 * Uses raw DB::table()->insert() in chunks rather than Eloquent — at 10k+
 * rows, hydrating/saving individual User models (bcrypt-hashing a password
 * per row, firing the geo-sync observer per row) is far slower than the
 * load test itself. The Redis GEO index is populated once at the end via
 * `drivers:rebuild-geo-index` instead of per-row.
 *
 * Not intended for production data — every row is tagged with a
 * `loadtest.` email prefix so `--fresh` can find and remove them safely.
 */
class SeedLoadTestUsers extends Command
{
    protected $signature = 'loadtest:seed
        {--drivers=10000 : Number of driver accounts to create}
        {--passengers=2000 : Number of passenger accounts to create}
        {--zones=20 : Number of city "hot spot" clusters drivers are distributed across}
        {--out=storage/app/loadtest/tokens.json : Where to write the exported token file}
        {--fresh : Delete any previously seeded loadtest.* accounts first}';

    protected $description = 'Bulk-seed driver/passenger accounts and export tokens for k6 load testing';

    // Phnom Penh bounding box — wide enough to spread hot spots across the
    // whole city instead of one dense cluster, so radius-tier dispatch
    // (2/4/6/8km) actually gets exercised at different densities.
    private const LAT_MIN = 11.45;
    private const LAT_MAX = 11.65;
    private const LNG_MIN = 104.85;
    private const LNG_MAX = 105.05;

    public function handle(): int
    {
        $driverCount    = (int) $this->option('drivers');
        $passengerCount = (int) $this->option('passengers');
        $zoneCount      = max(1, (int) $this->option('zones'));
        $outPath        = base_path($this->option('out'));

        if ($this->option('fresh')) {
            $this->info('Removing previously seeded loadtest.* accounts...');
            DB::table('vehicles')->whereIn('user_id', function ($q) {
                $q->select('id')->from('users')->where('email', 'like', 'loadtest.%@autoride.test');
            })->delete();
            DB::table('users')->where('email', 'like', 'loadtest.%@autoride.test')->delete();
        }

        $passwordHash = Hash::make('password'); // shared across all seeded rows — test data only
        $now          = now();

        // Zone centers spread across the bounding box; each driver jitters up
        // to ~2km around whichever zone it's assigned to.
        $zones = [];
        for ($z = 0; $z < $zoneCount; $z++) {
            $zones[] = [
                'lat' => self::LAT_MIN + mt_rand(0, 10000) / 10000 * (self::LAT_MAX - self::LAT_MIN),
                'lng' => self::LNG_MIN + mt_rand(0, 10000) / 10000 * (self::LNG_MAX - self::LNG_MIN),
            ];
        }

        $this->info("Seeding {$driverCount} drivers across {$zoneCount} zones...");
        $this->seedUsers('driver', $driverCount, $passwordHash, $now, function (int $i) use ($zones) {
            $zone     = $zones[$i % count($zones)];
            $angle    = mt_rand(0, 3600) / 3600 * 2 * M_PI;
            $distance = mt_rand(0, 2000) / 1000; // 0..2km
            $latOffset = ($distance / 111.0) * cos($angle);
            $lngOffset = ($distance / (111.0 * cos(deg2rad($zone['lat'])))) * sin($angle);

            return [
                'available'         => true,
                'current_latitude'  => round($zone['lat'] + $latOffset, 7),
                'current_longitude' => round($zone['lng'] + $lngOffset, 7),
                'rating'            => round(mt_rand(35, 50) / 10, 2),
                'total_ratings'     => mt_rand(5, 200),
                'wallet_balance'    => 500000,
            ];
        });

        $this->info("Seeding {$passengerCount} passengers...");
        $this->seedUsers('passenger', $passengerCount, $passwordHash, $now, fn () => [
            'wallet_balance' => 200000,
        ]);

        $this->info('Assigning one active vehicle per driver...');
        $this->seedVehicles($driverCount);

        $this->info('Rebuilding Redis GEO index from the newly seeded drivers...');
        Artisan::call('drivers:rebuild-geo-index');
        $this->line(Artisan::output());

        $this->exportTokens($outPath, $driverCount, $passengerCount);

        $this->info("Done. Tokens exported to {$outPath}");

        return self::SUCCESS;
    }

    /** @param callable(int): array $extraFields */
    private function seedUsers(string $role, int $count, string $passwordHash, $now, callable $extraFields): void
    {
        $chunkSize = 1000;
        $bar       = $this->output->createProgressBar($count);

        for ($start = 1; $start <= $count; $start += $chunkSize) {
            $rows = [];
            $end  = min($start + $chunkSize - 1, $count);

            for ($i = $start; $i <= $end; $i++) {
                $rows[] = array_merge([
                    'name'              => ucfirst($role) . " Load Test {$i}",
                    'email'             => "loadtest.{$role}{$i}@autoride.test",
                    'phone'             => '855' . str_pad((string) (20000000 + $i), 8, '0', STR_PAD_LEFT),
                    'password'          => $passwordHash,
                    'role'              => $role,
                    'api_token'         => "loadtest-{$role}-token-" . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'email_verified_at' => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ], $extraFields($i));
            }

            DB::table('users')->insert($rows);
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedVehicles(int $driverCount): void
    {
        // Map email -> id for just the drivers we seeded (indexes encoded in email).
        $ids = DB::table('users')
            ->where('role', 'driver')
            ->where('email', 'like', 'loadtest.driver%@autoride.test')
            ->pluck('id', 'email');

        $chunkSize = 1000;
        $bar       = $this->output->createProgressBar($driverCount);
        $now       = now();

        for ($start = 1; $start <= $driverCount; $start += $chunkSize) {
            $rows = [];
            $end  = min($start + $chunkSize - 1, $driverCount);

            for ($i = $start; $i <= $end; $i++) {
                $userId = $ids["loadtest.driver{$i}@autoride.test"] ?? null;
                if (! $userId) {
                    continue;
                }

                $rows[] = [
                    'user_id'       => $userId,
                    'license_plate' => 'LT-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'make'          => 'Honda',
                    'model'         => 'Load Test',
                    'year'          => 2022,
                    'type'          => 'standard',
                    'status'        => 'active',
                    'capacity'      => 4,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            if ($rows) {
                DB::table('vehicles')->insert($rows);
            }
            $bar->advance($end - $start + 1);
        }

        $bar->finish();
        $this->newLine();
    }

    private function exportTokens(string $outPath, int $driverCount, int $passengerCount): void
    {
        $drivers = DB::table('users')
            ->where('role', 'driver')
            ->where('email', 'like', 'loadtest.driver%@autoride.test')
            ->select('id', 'api_token', 'current_latitude as lat', 'current_longitude as lng')
            ->get();

        $passengers = DB::table('users')
            ->where('role', 'passenger')
            ->where('email', 'like', 'loadtest.passenger%@autoride.test')
            ->select('id', 'api_token')
            ->get();

        File::ensureDirectoryExists(dirname($outPath));
        File::put($outPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'drivers'      => $drivers,
            'passengers'   => $passengers,
        ], JSON_PRETTY_PRINT));
    }
}
