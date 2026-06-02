<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('driver_arrived_at')->nullable()->after('accepted_at');
            $table->timestamp('started_at')->nullable()->after('driver_arrived_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');

            // Allow surge_accepted to be stored so we know passenger confirmed surge.
            $table->boolean('surge_accepted')->default(false)->after('surge_zone_id');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_at', 'driver_arrived_at', 'started_at',
                'completed_at', 'cancelled_at', 'surge_accepted',
            ]);
        });
    }
};
