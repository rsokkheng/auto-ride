<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'         => 'Motorcycle',
                'slug'         => 'motorcycle',
                'icon'         => 'motorcycle',
                'description'  => 'Fast and affordable motorbike rides',
                'capacity'     => 1,
                'base_fare'    => 1.00,
                'per_km_fare'  => 0.50,
                'sort_order'   => 1,
            ],
            [
                'name'         => 'Tuk-Tuk',
                'slug'         => 'tuk-tuk',
                'icon'         => 'tuk-tuk',
                'description'  => 'Traditional tuk-tuk, great for short trips',
                'capacity'     => 3,
                'base_fare'    => 1.50,
                'per_km_fare'  => 0.70,
                'sort_order'   => 2,
            ],
            [
                'name'         => 'Car',
                'slug'         => 'car',
                'icon'         => 'car',
                'description'  => 'Comfortable sedan for everyday rides',
                'capacity'     => 4,
                'base_fare'    => 2.00,
                'per_km_fare'  => 1.00,
                'sort_order'   => 3,
            ],
            [
                'name'         => 'SUV',
                'slug'         => 'suv',
                'icon'         => 'suv',
                'description'  => 'Spacious SUV for groups or luggage',
                'capacity'     => 6,
                'base_fare'    => 3.00,
                'per_km_fare'  => 1.50,
                'sort_order'   => 4,
            ],
            [
                'name'         => 'Van',
                'slug'         => 'van',
                'icon'         => 'van',
                'description'  => 'Large van for group travel',
                'capacity'     => 10,
                'base_fare'    => 4.00,
                'per_km_fare'  => 2.00,
                'sort_order'   => 5,
            ],
            [
                'name'         => 'Electric',
                'slug'         => 'electric',
                'icon'         => 'electric',
                'description'  => 'Eco-friendly electric vehicle',
                'capacity'     => 4,
                'base_fare'    => 2.00,
                'per_km_fare'  => 0.90,
                'sort_order'   => 6,
            ],
        ];

        foreach ($types as $type) {
            VehicleType::updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
