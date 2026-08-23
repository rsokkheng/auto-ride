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
        // Pivot: which sizes are offered for which vehicle type (many-to-many since
        // e.g. 1.4M is valid for both Passenger and Cargo three-wheelers).
        Schema::create('marketplace_vehicle_type_size', function (Blueprint $table) {
            $table->id();
            // Explicit short constraint names — the default auto-generated name
            // ("marketplace_vehicle_type_size_marketplace_vehicle_type_id_foreign")
            // exceeds MySQL's 64-character identifier limit.
            $table->foreignId('marketplace_vehicle_type_id');
            $table->foreignId('marketplace_vehicle_size_id');
            $table->timestamps();

            $table->foreign('marketplace_vehicle_type_id', 'mvts_type_fk')
                ->references('id')->on('marketplace_vehicle_types')->cascadeOnDelete();
            $table->foreign('marketplace_vehicle_size_id', 'mvts_size_fk')
                ->references('id')->on('marketplace_vehicle_sizes')->cascadeOnDelete();
            $table->unique(['marketplace_vehicle_type_id', 'marketplace_vehicle_size_id'], 'mvts_type_size_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_vehicle_type_size');
    }
};
