<?php

namespace Database\Seeders;

use App\Models\MarketplaceVehicleSize;
use Illuminate\Database\Seeder;

class MarketplaceVehicleSizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sizes = [1.4, 1.6, 1.8, 2.2];

        foreach ($sizes as $i => $meters) {
            MarketplaceVehicleSize::updateOrCreate(
                ['value_meters' => $meters],
                [
                    'label'      => number_format($meters, 1) . 'M',
                    'sort_order' => $i + 1,
                    'active'     => true,
                ]
            );
        }

        $this->command?->info('Seeded ' . count($sizes) . ' marketplace vehicle sizes.');
    }
}
