<?php

namespace Database\Seeders;

use App\Models\MarketplaceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketplaceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'ធម្មតា',              // Normal
            'បើកបូល',              // Open roof
            'ដំបូលក្លុបបិទជិត',    // Closed cabin roof
        ];

        foreach ($categories as $i => $name) {
            MarketplaceCategory::updateOrCreate(
                ['slug' => Str::slug($name) ?: 'category-' . ($i + 1)],
                [
                    'name'       => $name,
                    'sort_order' => $i + 1,
                    'active'     => true,
                ]
            );
        }

        $this->command?->info('Seeded ' . count($categories) . ' marketplace categories.');
    }
}
