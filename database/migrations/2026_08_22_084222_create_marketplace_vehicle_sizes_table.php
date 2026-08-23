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
        Schema::create('marketplace_vehicle_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('label', 30);            // e.g. "1.4M"
            $table->decimal('value_meters', 4, 2)->unique(); // e.g. 1.40
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_vehicle_sizes');
    }
};
