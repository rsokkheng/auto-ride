<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('service_type')->unique(); // motorcycle, tuk_tuk, standard, premium, shared, van
            $table->string('label');                  // 🛵 Motorcycle
            $table->string('icon')->default('fa-car');
            $table->unsignedInteger('base');          // Base fare (KHR)
            $table->unsignedInteger('per_km');        // Per km rate (KHR)
            $table->unsignedInteger('per_min');       // Per minute traffic rate (KHR)
            $table->unsignedInteger('booking_fee');   // Flat booking fee (KHR)
            $table->unsignedInteger('minimum');       // Minimum fare floor (KHR)
            $table->unsignedTinyInteger('capacity');  // Max passengers
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Global pricing settings (night rate, avg speed, etc.)
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
        Schema::dropIfExists('ride_pricing');
    }
};
