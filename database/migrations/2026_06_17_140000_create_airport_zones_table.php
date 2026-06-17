<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airport_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('iata_code', 4)->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_meters')->default(2000);
            $table->unsignedInteger('surcharge_khr')->default(5000);
            $table->unsignedInteger('luggage_fee_khr')->default(2000);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed Phnom Penh International Airport
        DB::table('airport_zones')->insert([
            [
                'name'          => 'Phnom Penh International Airport',
                'iata_code'     => 'PNH',
                'latitude'      => 11.5466,
                'longitude'     => 104.8440,
                'radius_meters' => 2000,
                'surcharge_khr' => 5000,
                'luggage_fee_khr' => 2000,
                'active'        => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'name'          => 'Siem Reap Angkor International Airport',
                'iata_code'     => 'SAI',
                'latitude'      => 13.3967,
                'longitude'     => 103.8168,
                'radius_meters' => 2000,
                'surcharge_khr' => 5000,
                'luggage_fee_khr' => 2000,
                'active'        => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('airport_zones');
    }
};
