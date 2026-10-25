<?php

use App\Models\CarRental;
use App\Models\MarketplaceProduct;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product bookings were all saved as vehicle_type "sedan" because the type was
 * read from the product's vehicle_id (never set) instead of its marketplace
 * vehicle type. Re-derive it. Amounts already booked are left as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('car_rentals')
            ->whereNotNull('marketplace_product_id')
            ->where('vehicle_type', 'sedan')
            ->get(['id', 'marketplace_product_id']);

        $products = MarketplaceProduct::with(['vehicle', 'marketplaceVehicleType'])
            ->whereIn('id', $rows->pluck('marketplace_product_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $type = CarRental::vehicleTypeForProduct($products[$row->marketplace_product_id] ?? null);
            if ($type && $type !== 'sedan') {
                DB::table('car_rentals')->where('id', $row->id)->update(['vehicle_type' => $type]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible: the original values were all "sedan" by mistake.
    }
};
