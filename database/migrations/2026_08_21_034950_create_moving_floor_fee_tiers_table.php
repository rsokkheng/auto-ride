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
        Schema::create('moving_floor_fee_tiers', function (Blueprint $table) {
            $table->id();
            // Inclusive floor threshold this tier covers (e.g. 3 = "up to floor 3").
            // NULL means "no upper limit" — the catch-all top tier ("7+").
            $table->unsignedInteger('max_floor')->nullable();
            $table->unsignedInteger('fee');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moving_floor_fee_tiers');
    }
};
