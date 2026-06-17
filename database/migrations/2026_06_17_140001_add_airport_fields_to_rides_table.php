<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->boolean('is_airport_trip')->default(false)->after('service_type');
            $table->string('flight_number', 12)->nullable()->after('is_airport_trip');
            $table->string('terminal', 10)->nullable()->after('flight_number');
            $table->unsignedTinyInteger('luggage_count')->default(0)->after('terminal');
            $table->unsignedInteger('airport_surcharge_khr')->default(0)->after('luggage_count');
            $table->foreignId('airport_zone_id')->nullable()->constrained('airport_zones')->nullOnDelete()->after('airport_surcharge_khr');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['airport_zone_id']);
            $table->dropColumn(['is_airport_trip', 'flight_number', 'terminal', 'luggage_count', 'airport_surcharge_khr', 'airport_zone_id']);
        });
    }
};
