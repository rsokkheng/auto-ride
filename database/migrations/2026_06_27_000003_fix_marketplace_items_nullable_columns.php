<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Use raw ALTER TABLE to reliably make FK columns nullable on MySQL
        DB::statement('ALTER TABLE `marketplace_items` MODIFY `seller_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `marketplace_items` MODIFY `vehicle_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `marketplace_items` MODIFY `seller_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `marketplace_items` MODIFY `vehicle_id` BIGINT UNSIGNED NOT NULL');
    }
};
