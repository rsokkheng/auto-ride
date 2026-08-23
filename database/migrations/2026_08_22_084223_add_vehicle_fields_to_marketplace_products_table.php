<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up orphaned columns from an earlier, reverted iteration of this feature —
        // their FK constraints point at tables (product_vehicle_types/product_colors/
        // product_vehicle_sizes) that no longer exist. Only present on databases that
        // ran that now-reverted migration (e.g. the shared dev DB) — a fresh install
        // (e.g. the isolated test DB) never had these columns, so guard on existence.
        if (Schema::hasColumn('marketplace_products', 'product_vehicle_type_id')) {
            Schema::table('marketplace_products', function (Blueprint $table) {
                $table->dropForeign(['product_vehicle_type_id']);
                $table->dropForeign(['product_vehicle_size_id']);
                $table->dropForeign(['product_color_id']);
                $table->dropColumn(['product_vehicle_type_id', 'product_vehicle_size_id', 'product_color_id', 'color']);
            });
        }

        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->foreignId('marketplace_vehicle_type_id')->nullable()
                ->after('vehicle_id')->constrained('marketplace_vehicle_types')->nullOnDelete();
            $table->foreignId('marketplace_vehicle_color_id')->nullable()
                ->after('marketplace_vehicle_type_id')->constrained('marketplace_vehicle_colors')->nullOnDelete();
            $table->foreignId('marketplace_vehicle_size_id')->nullable()
                ->after('marketplace_vehicle_color_id')->constrained('marketplace_vehicle_sizes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketplace_vehicle_type_id');
            $table->dropConstrainedForeignId('marketplace_vehicle_color_id');
            $table->dropConstrainedForeignId('marketplace_vehicle_size_id');
        });
    }
};
