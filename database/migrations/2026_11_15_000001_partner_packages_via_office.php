<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner packages go through the ROTEH office in two legs:
 *   pickup leg   — staff driver: shop → office   (accepted → picked_up → at_office)
 *   delivery leg — staff driver: office → customer (accepted → picked_up → completed)
 * `leg` tells the two apart while the raw status is the same; NULL = packages
 * from before this change, which keep going straight to the customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'leg')) $table->string('leg', 10)->nullable()->after('status');
            if (! Schema::hasColumn('deliveries', 'pickup_driver_id')) $table->foreignId('pickup_driver_id')->nullable()->after('driver_id')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('deliveries', 'office_received_at')) $table->timestamp('office_received_at')->nullable()->after('pickup_scanned_at');
            if (! Schema::hasColumn('deliveries', 'office_received_by')) $table->foreignId('office_received_by')->nullable()->after('office_received_at')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('deliveries', 'office_dispatched_at')) $table->timestamp('office_dispatched_at')->nullable()->after('office_received_by');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_driver_id');
            $table->dropConstrainedForeignId('office_received_by');
            $table->dropColumn(['leg', 'office_received_at', 'office_dispatched_at']);
        });
    }
};
