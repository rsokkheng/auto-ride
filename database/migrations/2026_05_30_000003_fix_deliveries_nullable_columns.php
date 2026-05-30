<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix two bugs in the deliveries table:
 *
 * 1. package_details was TEXT NOT NULL with no default, causing every
 *    INSERT that omits the field (admin form, API) to fail with a
 *    MySQL constraint error → 500.
 *
 * 2. pickup_lat / pickup_lng were added to the Delivery model
 *    $fillable and $casts but the columns were never created → SQL
 *    error on any API delivery creation that includes coordinates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Make package_details nullable so inserts without it succeed.
            $table->text('package_details')->nullable()->default(null)->change();

            // Add coordinate columns for the driver-matching pickup point.
            $table->decimal('pickup_lat', 10, 7)->nullable()->after('dropoff_address');
            $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->text('package_details')->nullable(false)->change();
            $table->dropColumn(['pickup_lat', 'pickup_lng']);
        });
    }
};
