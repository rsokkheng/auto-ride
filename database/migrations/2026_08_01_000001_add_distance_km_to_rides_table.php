<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('fare');
            $table->unsignedSmallInteger('duration_min')->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['distance_km', 'duration_min']);
        });
    }
};
