<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who cancelled a delivery / moving job and when — shown to the customer. */
return new class extends Migration
{
    public function up(): void
    {
        // cancellation_reason is in the model's fillable but was never created
        // (an older migration assumed it existed).
        $hasReason = Schema::hasColumn('deliveries', 'cancellation_reason');

        Schema::table('deliveries', function (Blueprint $table) use ($hasReason) {
            if (! $hasReason) {
                $table->string('cancellation_reason', 255)->nullable();
            }
            $table->string('cancelled_by', 20)->nullable(); // sender | driver | admin
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['cancelled_by', 'cancelled_at']);
            // cancellation_reason is left in place — it may predate this migration.
        });
    }
};
