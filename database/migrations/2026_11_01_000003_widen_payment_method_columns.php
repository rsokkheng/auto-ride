<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment methods are now cash / ABA / ACLEDA / ROTEH Pay. These two tables
 * stored the method as an ENUM without 'acleda', so an ACLEDA order failed at
 * the database. Plain strings keep every existing value.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['marketplace_orders', 'car_rentals'] as $table) {
            if (Schema::hasColumn($table, 'payment_method')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('payment_method', 32)->default('cash')->change());
            }
        }
    }

    public function down(): void
    {
        // Left as strings — narrowing back to the old ENUM could drop data.
    }
};
