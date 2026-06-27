<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `marketplace_orders` MODIFY `seller_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `marketplace_orders` MODIFY `seller_id` BIGINT UNSIGNED NOT NULL');
    }
};
