<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partner packages: ROTEH charges the delivery fee to the Partner (taken from
 * the COD or paid by the Partner) — never to the receiver. Packages created
 * with "receiver pays" are corrected so statements show COD − fee. A cash fee
 * that was marked paid at completion is reopened: it is settled on the
 * Partner's daily statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('deliveries')->whereNotNull('partner_id')->where('payment_by', 'recipient')
            ->update(['payment_by' => 'sender']);

        DB::table('deliveries')->whereNotNull('partner_id')
            ->where(fn ($q) => $q->whereNull('payment_method')->orWhere('payment_method', 'cash'))
            ->where('payment_status', 'paid')
            ->update(['payment_status' => 'unpaid']);
    }

    public function down(): void
    {
        // Data correction — not reversible.
    }
};
