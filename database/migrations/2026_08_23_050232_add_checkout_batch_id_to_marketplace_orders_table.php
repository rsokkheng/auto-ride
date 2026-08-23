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
        Schema::table('marketplace_orders', function (Blueprint $table) {
            // Groups the order rows created by a single multi-item checkout (buying
            // several different listings at once) so they can be fetched/displayed
            // together. Null for orders placed the old single-item way.
            $table->uuid('checkout_batch_id')->nullable()->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropColumn('checkout_batch_id');
        });
    }
};
