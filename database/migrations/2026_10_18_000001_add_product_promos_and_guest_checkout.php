<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enum only allowed rides/deliveries/moving/all, so product coupons could never match.
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('service_type', 32)->default('all')->change();
        });

        Schema::table('promo_code_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_phone', 20)->nullable()->after('user_id')->index();
        });

        // guest_token is a random secret the app keeps on the device: it lets a
        // guest list/cancel their own orders and is never shown to anyone else.
        foreach (['marketplace_orders', 'car_rentals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('guest_token', 64)->nullable()->after('guest_phone')->index();
                $table->string('guest_fcm_token', 255)->nullable()->after('guest_token');
            });
        }
    }

    public function down(): void
    {
        foreach (['marketplace_orders', 'car_rentals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['guest_token']);
                $table->dropColumn(['guest_token', 'guest_fcm_token']);
            });
        }

        Schema::table('promo_code_usages', function (Blueprint $table) {
            $table->dropIndex(['guest_phone']);
            $table->dropColumn('guest_phone');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->enum('service_type', ['rides', 'deliveries', 'moving', 'all'])->default('all')->change();
        });
    }
};
