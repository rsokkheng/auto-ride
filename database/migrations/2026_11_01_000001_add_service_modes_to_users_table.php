<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which jobs a driver wants offered (the Ride / Delivery / Rental toggles in
 * the driver app). Dispatch and push alerts skip drivers with a mode off.
 * Default on, so drivers on an older app (that never sends modes) keep
 * getting every job type as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('accepts_rides')->default(true)->after('available');
            $table->boolean('accepts_deliveries')->default(true)->after('accepts_rides');
            $table->boolean('accepts_rentals')->default(true)->after('accepts_deliveries');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['accepts_rides', 'accepts_deliveries', 'accepts_rentals']);
        });
    }
};
