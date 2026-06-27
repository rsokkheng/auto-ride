<?php

namespace Database\Seeders;

use App\Models\CarRental;
use App\Models\User;
use Illuminate\Database\Seeder;

class CarRentalSeeder extends Seeder
{
    public function run(): void
    {
        $passenger1 = User::find(2); // Passenger Demo
        $passenger2 = User::find(4); // Ry Sokkheng

        if (! $passenger1) {
            $this->command->warn('User #2 not found.');
            return;
        }

        $samples = [
            // product_id 1 — Avatr 11 SUV ($120/day)
            [
                'user_id'                => $passenger1->id,
                'marketplace_product_id' => 1,
                'vehicle_type'           => 'suv',
                'pickup_location'        => 'Phnom Penh Airport, Kandal',
                'pickup_lat'             => 11.5564,
                'pickup_lng'             => 104.9282,
                'start_date'             => '2026-07-01',
                'end_date'               => '2026-07-03',
                'total_days'             => 3,
                'daily_rate_khr'         => 480000,  // $120 × 4000
                'total_amount_khr'       => 1440000, // 3 days
                'payment_method'         => 'cash',
                'status'                 => 'pending',
                'notes'                  => 'Please deliver to the airport arrival hall',
            ],

            // product_id 2 — MAZDA EZ60 ($120/day)
            [
                'user_id'                => $passenger1->id,
                'marketplace_product_id' => 2,
                'vehicle_type'           => 'sedan',
                'pickup_location'        => 'Siem Reap Hotel, Angkor Wat Road',
                'pickup_lat'             => 13.3671,
                'pickup_lng'             => 103.8448,
                'start_date'             => '2026-07-05',
                'end_date'               => '2026-07-07',
                'total_days'             => 3,
                'daily_rate_khr'         => 480000,
                'total_amount_khr'       => 1440000,
                'payment_method'         => 'aba',
                'status'                 => 'confirmed',
                'notes'                  => 'Family trip',
            ],

            // product_id 3 — RX 350h Luxury 2026 ($200/day)
            [
                'user_id'                => $passenger2 ? $passenger2->id : $passenger1->id,
                'marketplace_product_id' => 3,
                'vehicle_type'           => 'suv',
                'pickup_location'        => 'BKK Market, Phnom Penh',
                'pickup_lat'             => 11.5620,
                'pickup_lng'             => 104.9160,
                'start_date'             => '2026-07-10',
                'end_date'               => '2026-07-12',
                'total_days'             => 3,
                'daily_rate_khr'         => 800000,  // $200 × 4000
                'total_amount_khr'       => 2400000,
                'payment_method'         => 'wallet',
                'status'                 => 'completed',
                'notes'                  => null,
            ],

            // No marketplace product — generic motorcycle rental
            [
                'user_id'                => $passenger2 ? $passenger2->id : $passenger1->id,
                'marketplace_product_id' => null,
                'vehicle_type'           => 'motorcycle',
                'pickup_location'        => 'Russian Market, Phnom Penh',
                'pickup_lat'             => 11.5477,
                'pickup_lng'             => 104.9196,
                'start_date'             => '2026-07-15',
                'end_date'               => '2026-07-15',
                'total_days'             => 1,
                'daily_rate_khr'         => 20000,
                'total_amount_khr'       => 20000,
                'payment_method'         => 'cash',
                'status'                 => 'cancelled',
                'notes'                  => 'Changed plan',
            ],
        ];

        foreach ($samples as $data) {
            CarRental::create($data);
        }

        $this->command->info('CarRentalSeeder: ' . count($samples) . ' rentals inserted.');
        $this->command->table(
            ['User', 'Product ID', 'Vehicle', 'Days', 'Status'],
            collect($samples)->map(fn($s) => [
                $s['user_id'],
                $s['marketplace_product_id'] ?? '—',
                $s['vehicle_type'],
                $s['total_days'],
                $s['status'],
            ])->toArray()
        );
    }
}
