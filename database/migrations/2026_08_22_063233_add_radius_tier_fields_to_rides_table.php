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
        Schema::table('rides', function (Blueprint $table) {
            // Index into config('ride.radius_tiers_km') — which expanding-radius search ring
            // (e.g. 2km/4km/6km/8km) the ranked dispatch is currently trying.
            $table->unsignedTinyInteger('dispatch_tier')->default(0)->after('dispatch_position');
            // Cumulative driver_ids already offered across all tiers so far, so widening the
            // radius never re-offers someone already tried (and skipped/rejected/timed out).
            $table->json('tried_driver_ids')->nullable()->after('dispatch_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['dispatch_tier', 'tried_driver_ids']);
        });
    }
};
