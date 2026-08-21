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
            // Set once the ranked dispatch queue is exhausted — while NULL the ride is still
            // in ranked-only dispatch and must not appear in the self-serve /rides/available list.
            $table->timestamp('self_serve_expires_at')->nullable()->after('dispatch_offered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn('self_serve_expires_at');
        });
    }
};
