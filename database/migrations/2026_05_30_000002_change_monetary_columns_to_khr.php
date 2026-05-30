<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convert all monetary columns from decimal(10,2) USD to
 * unsigned big integer storing whole Khmer Riel (KHR, ៛).
 * KHR has no sub-unit in practical use — amounts are whole numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->unsignedBigInteger('fare')->default(0)->change();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('fee')->default(0)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->change();
        });

        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->default(0)->change();
            $table->unsignedBigInteger('rent_rate')->nullable()->default(null)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_balance')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->decimal('fare', 10, 2)->default(0)->change();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('fee', 10, 2)->default(0)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->change();
            $table->decimal('rent_rate', 10, 2)->nullable()->default(null)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('wallet_balance', 10, 2)->default(0)->change();
        });
    }
};
