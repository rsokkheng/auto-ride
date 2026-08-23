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
        // Pivot: which colors are offered for which vehicle type (many-to-many —
        // currently every color applies to every type, but the structure allows
        // restricting specific colors to specific types later).
        Schema::create('marketplace_vehicle_type_color', function (Blueprint $table) {
            $table->id();
            // Explicit short constraint names — the default auto-generated name
            // exceeds MySQL's 64-character identifier limit on this long table name.
            $table->foreignId('marketplace_vehicle_type_id');
            $table->foreignId('marketplace_vehicle_color_id');
            $table->timestamps();

            $table->foreign('marketplace_vehicle_type_id', 'mvtcol_type_fk')
                ->references('id')->on('marketplace_vehicle_types')->cascadeOnDelete();
            $table->foreign('marketplace_vehicle_color_id', 'mvtcol_color_fk')
                ->references('id')->on('marketplace_vehicle_colors')->cascadeOnDelete();
            $table->unique(['marketplace_vehicle_type_id', 'marketplace_vehicle_color_id'], 'mvtcol_type_color_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_vehicle_type_color');
    }
};
