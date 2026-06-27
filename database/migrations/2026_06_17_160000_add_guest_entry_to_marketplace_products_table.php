<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            // Make seller_id nullable so guests can list without an account
            $table->foreignId('seller_id')->nullable()->change();

            $table->enum('entry_type', ['user', 'guest'])->default('user')->after('seller_id');
            $table->string('guest_name', 100)->nullable()->after('entry_type');
            $table->string('guest_phone', 20)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'guest_name', 'guest_phone']);
            $table->foreignId('seller_id')->nullable(false)->change();
        });
    }
};
