<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            // Make buyer_id nullable so guests can place orders
            $table->foreignId('buyer_id')->nullable()->change();

            $table->enum('entry_type', ['user', 'guest'])->default('user')->after('buyer_id');
            $table->string('guest_name', 100)->nullable()->after('entry_type');
            $table->string('guest_phone', 20)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'guest_name', 'guest_phone']);
            $table->foreignId('buyer_id')->nullable(false)->change();
        });
    }
};
