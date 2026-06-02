<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->decimal('pickup_lat',  10, 7)->nullable()->after('dropoff_address');
            $table->decimal('pickup_lng',  10, 7)->nullable()->after('pickup_lat');
            $table->decimal('dropoff_lat', 10, 7)->nullable()->after('pickup_lng');
            $table->decimal('dropoff_lng', 10, 7)->nullable()->after('dropoff_lat');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng']);
        });
    }
};
