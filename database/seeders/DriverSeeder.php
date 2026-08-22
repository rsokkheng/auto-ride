<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds 100 tuk-tuk test drivers clustered tightly around Phnom Penh, each with
 * GPS coordinates, an active tuk_tuk vehicle, and a fixed api_token — for
 * testing ranked dispatch, self-serve fallback, and the "nearby drivers" map
 * endpoint with a dense, realistic-looking cluster (all the same vehicle type,
 * like a busy tuk-tuk stand).
 */
class DriverSeeder extends Seeder
{
    // Phnom Penh center (matches the pickup point used in prior manual tests).
    private const CENTER_LAT = 11.5564;
    private const CENTER_LNG = 104.9282;

    private const COUNT = 100;

    public function run(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            // Tight cluster within ~0.1km–2km of the center — all close to each other,
            // like tuk-tuks parked/circling around a busy pickup area.
            $angle    = deg2rad(($i * 37) % 360); // spread bearings evenly-ish without a visible grid
            $distance = 0.1 + (($i - 1) % 20) * 0.1; // 0.1km .. 2.0km
            $latOffset = ($distance / 111.0) * cos($angle);
            $lngOffset = ($distance / (111.0 * cos(deg2rad(self::CENTER_LAT)))) * sin($angle);

            $driver = User::updateOrCreate(
                ['email' => "seed.driver{$i}@autoride.test"],
                [
                    'name'              => "Seed Driver {$i}",
                    'phone'             => '855' . str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                    'password'          => Hash::make('password'),
                    'role'              => 'driver',
                    'available'         => true,
                    'current_latitude'  => self::CENTER_LAT + $latOffset,
                    'current_longitude' => self::CENTER_LNG + $lngOffset,
                    'rating'            => round(mt_rand(35, 50) / 10, 2), // 3.5–5.0
                    'total_ratings'     => mt_rand(5, 200),
                    'api_token'         => 'seed-driver-token-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                ]
            );

            // Not in User's fillable list, so it's set outside mass assignment.
            if (! $driver->email_verified_at) {
                $driver->forceFill(['email_verified_at' => now()])->save();
            }

            Vehicle::updateOrCreate(
                ['user_id' => $driver->id],
                [
                    'license_plate' => '2AB-' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
                    'make'          => 'Honda',
                    'model'         => 'Tuk-tuk ' . $i,
                    'year'          => 2020 + ($i % 5),
                    'type'          => 'tuk_tuk',
                    'status'        => 'active',
                    'capacity'      => 3,
                ]
            );
        }

        $this->command?->info('Seeded 100 tuk-tuk test drivers (seed.driver1@autoride.test .. seed.driver100@autoride.test), api_token: seed-driver-token-001 .. -100');
    }
}
