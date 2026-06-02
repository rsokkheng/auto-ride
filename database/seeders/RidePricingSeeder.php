<?php

namespace Database\Seeders;

use App\Models\PricingSetting;
use App\Models\RidePricing;
use Illuminate\Database\Seeder;

class RidePricingSeeder extends Seeder
{
    public function run(): void
    {
        $rides = [
            [
                'service_type' => 'motorcycle',
                'label'        => 'Motorcycle',
                'icon'         => 'fa-motorcycle',
                'base'         => 2500,
                'per_km'       => 700,
                'per_min'      => 150,
                'booking_fee'  => 500,
                'minimum'      => 3000,
                'capacity'     => 1,
                'active'       => true,
            ],
            [
                'service_type' => 'tuk_tuk',
                'label'        => 'Tuk-tuk',
                'icon'         => 'fa-taxi',
                'base'         => 3500,
                'per_km'       => 900,
                'per_min'      => 200,
                'booking_fee'  => 500,
                'minimum'      => 4500,
                'capacity'     => 3,
                'active'       => true,
            ],
            [
                'service_type' => 'standard',
                'label'        => 'Car Standard',
                'icon'         => 'fa-car',
                'base'         => 5000,
                'per_km'       => 1500,
                'per_min'      => 250,
                'booking_fee'  => 1000,
                'minimum'      => 7000,
                'capacity'     => 4,
                'active'       => true,
            ],
            [
                'service_type' => 'premium',
                'label'        => 'Car Premium',
                'icon'         => 'fa-car-side',
                'base'         => 8000,
                'per_km'       => 3000,
                'per_min'      => 400,
                'booking_fee'  => 1000,
                'minimum'      => 12000,
                'capacity'     => 4,
                'active'       => true,
            ],
            [
                'service_type' => 'shared',
                'label'        => 'Shared Ride',
                'icon'         => 'fa-people-group',
                'base'         => 2500,
                'per_km'       => 1000,
                'per_min'      => 150,
                'booking_fee'  => 500,
                'minimum'      => 4000,
                'capacity'     => 4,
                'active'       => true,
            ],
            [
                'service_type' => 'van',
                'label'        => 'Van / XL',
                'icon'         => 'fa-van-shuttle',
                'base'         => 7000,
                'per_km'       => 2200,
                'per_min'      => 350,
                'booking_fee'  => 1000,
                'minimum'      => 10000,
                'capacity'     => 7,
                'active'       => true,
            ],
        ];

        foreach ($rides as $row) {
            RidePricing::updateOrCreate(['service_type' => $row['service_type']], $row);
        }

        $settings = [
            [
                'key'         => 'night_surcharge_rate',
                'value'       => '0.20',
                'label'       => 'Night Surcharge Rate',
                'description' => 'Extra % added to fare from 22:00–05:00 (e.g. 0.20 = +20%)',
            ],
            [
                'key'         => 'avg_city_speed_kmh',
                'value'       => '30',
                'label'       => 'Average City Speed (km/h)',
                'description' => 'Used for ETA fallback when Google Maps is unavailable',
            ],
            [
                'key'         => 'traffic_speed_threshold_kmh',
                'value'       => '20',
                'label'       => 'Traffic Speed Threshold (km/h)',
                'description' => 'Per-minute surcharge applied when avg speed is below this',
            ],
            [
                'key'         => 'delivery_night_surcharge_rate',
                'value'       => '0.15',
                'label'       => 'Delivery Night Surcharge Rate',
                'description' => 'Extra % on delivery fee from 22:00–05:00',
            ],
        ];

        foreach ($settings as $row) {
            PricingSetting::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
