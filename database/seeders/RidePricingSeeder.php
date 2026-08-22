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
            [
                'key'         => 'delivery_fee_base',
                'value'       => '3000',
                'label'       => 'Delivery Base Fee',
                'description' => 'Flat fee (KHR) applied to every package delivery',
            ],
            [
                'key'         => 'delivery_fee_per_km',
                'value'       => '1200',
                'label'       => 'Delivery Per-KM Rate',
                'description' => 'KHR charged per km of distance for package delivery',
            ],
            [
                'key'         => 'delivery_fee_surcharge_small',
                'value'       => '0',
                'label'       => 'Delivery Surcharge — Small Package',
                'description' => 'Extra KHR added for small packages',
            ],
            [
                'key'         => 'delivery_fee_surcharge_medium',
                'value'       => '2000',
                'label'       => 'Delivery Surcharge — Medium Package',
                'description' => 'Extra KHR added for medium packages',
            ],
            [
                'key'         => 'delivery_fee_surcharge_large',
                'value'       => '5000',
                'label'       => 'Delivery Surcharge — Large Package',
                'description' => 'Extra KHR added for large packages',
            ],
            [
                'key'         => 'delivery_match_radius_km',
                'value'       => '30',
                'label'       => 'Delivery Dispatch Radius (km)',
                'description' => 'Max distance from pickup to search for drivers when a delivery is requested',
            ],
            [
                'key'         => 'ride_radius_tiers_km',
                'value'       => '2,4,6,8',
                'label'       => 'Ride Dispatch Radius Tiers (km)',
                'description' => 'Comma-separated expanding search radii, narrowest first — e.g. "2,4,6,8" tries 2km, then 4km, 6km, 8km before falling to self-serve',
            ],
            [
                'key'         => 'ride_dispatch_limit',
                'value'       => '10',
                'label'       => 'Ride Dispatch Queue Size',
                'description' => 'Max number of ranked drivers queued for sequential dispatch per ride',
            ],
            [
                'key'         => 'ride_offer_timeout_seconds',
                'value'       => '15',
                'label'       => 'Ride Offer Timeout (seconds)',
                'description' => 'How long a driver has to accept before the ride is offered to the next driver in the queue',
            ],
            [
                'key'         => 'ride_self_serve_window_seconds',
                'value'       => '60',
                'label'       => 'Ride Self-Serve Window (seconds)',
                'description' => 'After the ranked queue is exhausted, how long the ride stays open for any driver to self-serve before auto-cancelling',
            ],
            [
                'key'         => 'driver_match_distance_weight',
                'value'       => '6',
                'label'       => 'Driver Match — Distance Weight',
                'description' => 'Score penalty per km when ranking drivers (higher = favour closer drivers more)',
            ],
            [
                'key'         => 'driver_match_eta_weight',
                'value'       => '1.5',
                'label'       => 'Driver Match — ETA Weight',
                'description' => 'Score penalty per ETA-minute when ranking drivers (higher = favour faster arrival more)',
            ],
            [
                'key'         => 'driver_match_rating_weight',
                'value'       => '4',
                'label'       => 'Driver Match — Rating Weight',
                'description' => 'Score penalty per rating-point below 5.0 when ranking drivers (higher = favour highly-rated drivers more)',
            ],
        ];

        foreach ($settings as $row) {
            PricingSetting::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
