<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            // Allow null so guests don't need a seller account, and vehicle is optional
            $table->unsignedBigInteger('seller_id')->nullable()->change();
            $table->unsignedBigInteger('vehicle_id')->nullable()->change();
            $table->string('entry_type')->default('user')->after('seller_id');
            $table->string('guest_name')->nullable()->after('entry_type');
            $table->string('guest_phone')->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'guest_name', 'guest_phone']);
            $table->unsignedBigInteger('seller_id')->nullable(false)->change();
            $table->unsignedBigInteger('vehicle_id')->nullable(false)->change();
        });
    }
};
