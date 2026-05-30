<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 24)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();

            // % of trip fare the platform keeps from this company's drivers.
            // null = use config default.
            $table->decimal('platform_commission_rate', 5, 2)->nullable();

            // % of trip fare that goes to the company (on top of platform fee).
            // Applies to rental and employee drivers.
            $table->decimal('company_commission_rate', 5, 2)->default(10.00);

            // Daily vehicle rental fee charged to rental-type drivers (KHR).
            $table->unsignedBigInteger('rental_daily_rate')->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
