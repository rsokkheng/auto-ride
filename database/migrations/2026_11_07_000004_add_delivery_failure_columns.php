<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery outcomes besides "completed": the driver could not deliver
 * (cannot contact receiver, refused, wrong address…) and brings the package
 * back to the shop — statuses delivery_failed → returned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'failure_reason')) $table->string('failure_reason', 30)->nullable()->after('cancellation_reason');
            if (! Schema::hasColumn('deliveries', 'failure_note'))   $table->string('failure_note', 500)->nullable()->after('failure_reason');
            if (! Schema::hasColumn('deliveries', 'failed_at'))      $table->timestamp('failed_at')->nullable()->after('completed_at');
            if (! Schema::hasColumn('deliveries', 'returned_at'))    $table->timestamp('returned_at')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            foreach (['failure_reason', 'failure_note', 'failed_at', 'returned_at'] as $c) {
                if (Schema::hasColumn('deliveries', $c)) $table->dropColumn($c);
            }
        });
    }
};
