<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surge_zones', function (Blueprint $table) {
            // JSON array of ISO weekday numbers: 0 = Sunday … 6 = Saturday.
            // null = every day.  [1,2,3,4,5] = Mon–Fri.  [0,6] = weekends.
            $table->json('schedule_days')->nullable()->after('ends_at');

            // Daily time window (24-h, UTC stored — display in local TZ on front-end).
            // null = all day.
            $table->time('schedule_start_time')->nullable()->after('schedule_days');
            $table->time('schedule_end_time')->nullable()->after('schedule_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('surge_zones', function (Blueprint $table) {
            $table->dropColumn(['schedule_days', 'schedule_start_time', 'schedule_end_time']);
        });
    }
};
