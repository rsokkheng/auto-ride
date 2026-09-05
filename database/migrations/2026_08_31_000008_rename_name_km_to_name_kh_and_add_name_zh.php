<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * doctrine/dbal is not installed in this project, so Schema::renameColumn()
     * is unavailable — rename via raw SQL instead.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE marketplace_vehicle_types RENAME COLUMN name_km TO name_kh');
        Schema::table('marketplace_vehicle_types', function (Blueprint $table) {
            $table->string('name_zh', 100)->nullable();
        });

        DB::statement('ALTER TABLE marketplace_vehicle_colors RENAME COLUMN name_km TO name_kh');
        Schema::table('marketplace_vehicle_colors', function (Blueprint $table) {
            $table->string('name_zh', 50)->nullable();
        });

        DB::statement('ALTER TABLE marketplace_product_accessories RENAME COLUMN name_km TO name_kh');
        Schema::table('marketplace_product_accessories', function (Blueprint $table) {
            $table->string('name_zh')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_vehicle_types', function (Blueprint $table) {
            $table->dropColumn('name_zh');
        });
        DB::statement('ALTER TABLE marketplace_vehicle_types RENAME COLUMN name_kh TO name_km');

        Schema::table('marketplace_vehicle_colors', function (Blueprint $table) {
            $table->dropColumn('name_zh');
        });
        DB::statement('ALTER TABLE marketplace_vehicle_colors RENAME COLUMN name_kh TO name_km');

        Schema::table('marketplace_product_accessories', function (Blueprint $table) {
            $table->dropColumn('name_zh');
        });
        DB::statement('ALTER TABLE marketplace_product_accessories RENAME COLUMN name_kh TO name_km');
    }
};
