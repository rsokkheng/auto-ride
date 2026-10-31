<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real fare components a completed ride was priced with (base, booking
 * fee, distance, time, surcharges, surge/minimum top-up) — so the driver
 * summary and the passenger receipt show the same figures instead of each
 * app guessing a split of the total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->json('fare_breakdown')->nullable()->after('fare');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn('fare_breakdown');
        });
    }
};
