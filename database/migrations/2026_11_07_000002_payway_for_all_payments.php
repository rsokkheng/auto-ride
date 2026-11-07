<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABA PayWay for every payment (rides, deliveries, marketplace, car rentals,
 * subscriptions), not only wallet top-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payway_transactions', function (Blueprint $table) {
            // Marketplace is priced in USD (cents), everything else in whole riel.
            $table->decimal('amount', 12, 2)->change();
            // Guest marketplace / rental checkouts have no account.
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_token', 64)->nullable()->after('user_id')->index();
        });

        if (! Schema::hasColumn('car_rentals', 'payment_status')) {
            Schema::table('car_rentals', fn (Blueprint $t) => $t->string('payment_status', 20)->default('unpaid')->after('payment_method'));
        }

        // 'pending' = waiting for the ABA payment before the plan starts.
        Schema::table('user_subscriptions', fn (Blueprint $t) => $t->string('status', 16)->default('active')->change());
        Schema::table('subscription_transactions', fn (Blueprint $t) => $t->string('status', 16)->default('paid')->change());

        // Rides / deliveries gain 'awaiting_payment' (finished, ABA not paid yet)
        // and 'prepaid' (paid before the trip) — wider than the old 16 chars.
        Schema::table('rides', fn (Blueprint $t) => $t->string('payment_status', 20)->default('unpaid')->change());
        Schema::table('deliveries', fn (Blueprint $t) => $t->string('payment_status', 20)->default('unpaid')->change());
        Schema::table('marketplace_orders', fn (Blueprint $t) => $t->string('payment_status', 20)->default('unpaid')->change());
    }

    public function down(): void
    {
        Schema::table('payway_transactions', function (Blueprint $table) {
            $table->dropIndex(['guest_token']);
            $table->dropColumn('guest_token');
        });
        if (Schema::hasColumn('car_rentals', 'payment_status')) {
            Schema::table('car_rentals', fn (Blueprint $t) => $t->dropColumn('payment_status'));
        }
    }
};
