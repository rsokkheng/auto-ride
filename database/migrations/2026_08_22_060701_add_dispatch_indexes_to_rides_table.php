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
            // Speeds up GET /v1/rides/available: WHERE status=? AND driver_id IS NULL
            // AND self_serve_expires_at > NOW(), which scans the whole table without this.
            $table->index(['status', 'driver_id', 'self_serve_expires_at'], 'rides_self_serve_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex('rides_self_serve_lookup_index');
        });
    }
};
