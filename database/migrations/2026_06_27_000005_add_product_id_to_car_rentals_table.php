<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_rentals', function (Blueprint $table) {
            $table->foreignId('marketplace_product_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('marketplace_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('car_rentals', function (Blueprint $table) {
            $table->dropForeign(['marketplace_product_id']);
            $table->dropColumn('marketplace_product_id');
        });
    }
};
