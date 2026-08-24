<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshots name_en/name_km/price at order time (not just a foreign
        // key to marketplace_product_accessories) so a later price change or
        // deletion on the listing doesn't rewrite what the buyer already paid.
        Schema::create('marketplace_order_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->foreignId('accessory_id')->nullable()
                ->constrained('marketplace_product_accessories')->nullOnDelete();
            $table->string('name_en');
            $table->string('name_km')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->decimal('accessories_total', 10, 2)->default(0)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropColumn('accessories_total');
        });
        Schema::dropIfExists('marketplace_order_accessories');
    }
};
