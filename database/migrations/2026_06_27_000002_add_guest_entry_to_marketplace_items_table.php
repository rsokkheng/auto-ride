<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            // Drop the NOT NULL foreign key constraint so guests don't need a seller account
            $table->foreignId('seller_id')->nullable()->change();
            $table->string('entry_type')->default('user')->after('seller_id');
            $table->string('guest_name')->nullable()->after('entry_type');
            $table->string('guest_phone')->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'guest_name', 'guest_phone']);
            $table->foreignId('seller_id')->nullable(false)->change();
        });
    }
};
