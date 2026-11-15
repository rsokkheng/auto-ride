<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Closing: money can change hands by bank as well as cash.
 *   driver_closings  — the driver hands the cash they collected to ROTEH in cash, by bank transfer, or both
 *   partner_closings — the balance is settled in cash, by bank, or split cash + bank
 * Existing closings were cash (driver) / their recorded settle_method (partner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_closings', function (Blueprint $table) {
            $table->string('handover_method', 10)->default('cash')->after('received_cash_usd');   // cash | bank | both
            $table->unsignedBigInteger('received_bank_khr')->default(0)->after('handover_method');
            $table->decimal('received_bank_usd', 12, 2)->default(0)->after('received_bank_khr');
            $table->string('handover_bank_name', 40)->nullable()->after('received_bank_usd');
            $table->string('handover_bank_ref', 100)->nullable()->after('handover_bank_name');
        });
        Schema::table('partner_closings', function (Blueprint $table) {
            $table->unsignedBigInteger('settle_cash_khr')->default(0)->after('settle_method');
            $table->unsignedBigInteger('settle_bank_khr')->default(0)->after('settle_cash_khr');
            $table->string('settle_bank_name', 40)->nullable()->after('settle_bank_khr');
        });
    }

    public function down(): void
    {
        Schema::table('driver_closings', fn (Blueprint $t) => $t->dropColumn(['handover_method', 'received_bank_khr', 'received_bank_usd', 'handover_bank_name', 'handover_bank_ref']));
        Schema::table('partner_closings', fn (Blueprint $t) => $t->dropColumn(['settle_cash_khr', 'settle_bank_khr', 'settle_bank_name']));
    }
};
