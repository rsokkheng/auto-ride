<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unsuccessful Partner packages go back to ROTEH first (driver taps Finish),
 * then ROTEH hands them back to the shop at the partner close:
 *   delivery_failed → at_roteh (hub_returned_at) → returned (returned_at)
 * ROTEH charges the delivery fee even when the delivery fails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'hub_returned_at')) $table->timestamp('hub_returned_at')->nullable()->after('failed_at');
        });
        Schema::table('driver_closings', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_closings', 'returns_received')) $table->boolean('returns_received')->default(false)->after('bank_verified');
        });

        // Failed deliveries are charged the full fee (still editable in Pricing).
        DB::table('pricing_settings')->where('key', 'partner_failed_fee_percent')->where('value', '0')->update(['value' => '100']);
        DB::table('pricing_settings')->where('key', 'partner_failed_fee_percent')->update([
            'description' => 'Percent of the delivery fee charged to the partner when a delivery was attempted but failed (package returned). Default 100 = full fee. Cancellations before pickup are never charged.',
        ]);
    }

    public function down(): void
    {
        Schema::table('deliveries', fn (Blueprint $t) => $t->dropColumn('hub_returned_at'));
        Schema::table('driver_closings', fn (Blueprint $t) => $t->dropColumn('returns_received'));
    }
};
