<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // marketplace_products: price fields → decimal USD
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->change();
            $table->decimal('rent_price_per_day', 10, 2)->nullable()->change();
        });

        // marketplace_orders: price fields → decimal USD
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('total_price', 10, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->default(0)->change();
            $table->unsignedBigInteger('rent_price_per_day')->nullable()->change();
        });

        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price')->change();
            $table->unsignedBigInteger('total_price')->change();
        });
    }
};
