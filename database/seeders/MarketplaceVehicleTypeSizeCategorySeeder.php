<?php

namespace Database\Seeders;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceVehicleSize;
use App\Models\MarketplaceVehicleType;
use Illuminate\Database\Seeder;

/**
 * Which sizes and body-style categories are valid for each vehicle type:
 *   - Passenger Three-Wheeler → sizes 1.4M/1.6M, category ធម្មតា (normal)
 *   - Cargo Three-Wheeler     → sizes 1.4M/1.8M/2.2M, categories បើកបូល (open roof) / ដំបូលក្លុបបិទជិត (closed cabin)
 *
 * Requires MarketplaceVehicleTypeSeeder, MarketplaceVehicleSizeSeeder, and
 * MarketplaceCategorySeeder to have run first.
 */
class MarketplaceVehicleTypeSizeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'passenger-three-wheeler' => [
                'sizes'      => [1.4, 1.6],
                'categories' => ['ធម្មតា'],
            ],
            'cargo-three-wheeler' => [
                'sizes'      => [1.4, 1.8, 2.2],
                'categories' => ['បើកបូល', 'ដំបូលក្លុបបិទជិត'],
            ],
        ];

        foreach ($map as $slug => $rules) {
            $type = MarketplaceVehicleType::where('slug', $slug)->first();

            if (! $type) {
                $this->command?->warn("Skipped \"{$slug}\" — vehicle type not found. Run MarketplaceVehicleTypeSeeder first.");
                continue;
            }

            $sizeIds = MarketplaceVehicleSize::whereIn('value_meters', $rules['sizes'])->pluck('id');
            $type->sizes()->sync($sizeIds);

            $categoryIds = MarketplaceCategory::whereIn('name', $rules['categories'])->pluck('id');
            $type->categories()->sync($categoryIds);
        }

        $this->command?->info('Seeded marketplace vehicle type → size/category mappings.');
    }
}
