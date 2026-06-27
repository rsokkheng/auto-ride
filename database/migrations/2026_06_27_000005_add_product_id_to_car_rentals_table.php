<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add marketplace_product_id after vehicle_id
        DB::statement('ALTER TABLE `car_rentals` ADD COLUMN `marketplace_product_id` BIGINT UNSIGNED NULL AFTER `vehicle_id`');
        DB::statement('ALTER TABLE `car_rentals` ADD CONSTRAINT `car_rentals_marketplace_product_id_foreign` FOREIGN KEY (`marketplace_product_id`) REFERENCES `marketplace_products`(`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `car_rentals` DROP FOREIGN KEY `car_rentals_marketplace_product_id_foreign`');
        DB::statement('ALTER TABLE `car_rentals` DROP COLUMN `marketplace_product_id`');
    }
};
