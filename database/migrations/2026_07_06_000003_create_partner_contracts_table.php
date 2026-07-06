<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('base_fee')->default(3000)->comment('Flat base fee per delivery (KHR)');
            $table->unsignedInteger('per_km_rate')->default(1200)->comment('Fee per km (KHR)');
            $table->unsignedInteger('surcharge_small')->default(0)->comment('Small package surcharge (KHR)');
            $table->unsignedInteger('surcharge_medium')->default(2000)->comment('Medium package surcharge (KHR)');
            $table->unsignedInteger('surcharge_large')->default(5000)->comment('Large package surcharge (KHR)');
            $table->unsignedInteger('min_fee')->default(3000)->comment('Minimum fee per delivery (KHR)');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contracts');
    }
};
