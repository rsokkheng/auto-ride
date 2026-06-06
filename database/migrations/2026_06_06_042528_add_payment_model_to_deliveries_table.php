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
        Schema::table('deliveries', function (Blueprint $table) {
            // customer_pays | partner_pays | split_payment | sponsored
            $table->string('payment_model', 20)->default('customer_pays')->after('payment_status');

            // Split payment: percentage the customer covers (0–100). Other party pays the rest.
            $table->unsignedTinyInteger('split_pct_customer')->nullable()->after('payment_model');

            // Partner / sponsor reference (company name, partner code, platform note, etc.)
            $table->string('partner_reference', 150)->nullable()->after('split_pct_customer');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['payment_model', 'split_pct_customer', 'partner_reference']);
        });
    }
};
