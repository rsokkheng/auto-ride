<?php

namespace Database\Seeders;

use App\Models\MarketplaceVehicleColor;
use Illuminate\Database\Seeder;

class MarketplaceVehicleColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colors = [
            ['name_en' => 'Black', 'name_km' => 'ខ្មៅ',   'code' => 'black'],
            ['name_en' => 'Red',   'name_km' => 'ក្រហម',  'code' => 'red'],
            ['name_en' => 'Blue',  'name_km' => 'ខៀវ',    'code' => 'blue'],
            ['name_en' => 'Gray',  'name_km' => 'ប្រផេះ', 'code' => 'gray'],
        ];

        foreach ($colors as $i => $color) {
            MarketplaceVehicleColor::updateOrCreate(
                ['code' => $color['code']],
                [
                    'name_en'    => $color['name_en'],
                    'name_km'    => $color['name_km'],
                    'sort_order' => $i + 1,
                    'active'     => true,
                ]
            );
        }

        $this->command?->info('Seeded ' . count($colors) . ' marketplace vehicle colors.');
    }
}
