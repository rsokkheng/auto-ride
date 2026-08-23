<?php

namespace Database\Seeders;

use App\Models\MarketplaceVehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketplaceVehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name_km' => 'កង់បីអ្នកដំណើរ', 'name_en' => 'Passenger Three-Wheeler'],
            ['name_km' => 'កង់បីដឹកទំនិញ',   'name_en' => 'Cargo Three-Wheeler'],
        ];

        foreach ($types as $i => $type) {
            MarketplaceVehicleType::updateOrCreate(
                ['slug' => Str::slug($type['name_en'])],
                [
                    'name_km'    => $type['name_km'],
                    'name_en'    => $type['name_en'],
                    'sort_order' => $i + 1,
                    'active'     => true,
                ]
            );
        }

        $this->command?->info('Seeded ' . count($types) . ' marketplace vehicle types.');
    }
}
