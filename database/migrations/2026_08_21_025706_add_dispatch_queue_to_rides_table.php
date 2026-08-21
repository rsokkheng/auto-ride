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
            // Ranked driver_ids (best match first) computed once at ride creation.
            $table->json('dispatch_queue')->nullable()->after('driver_id');
            // Index into dispatch_queue of the driver currently being offered the ride.
            $table->unsignedInteger('dispatch_position')->default(0)->after('dispatch_queue');
            $table->timestamp('dispatch_offered_at')->nullable()->after('dispatch_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['dispatch_queue', 'dispatch_position', 'dispatch_offered_at']);
        });
    }
};
