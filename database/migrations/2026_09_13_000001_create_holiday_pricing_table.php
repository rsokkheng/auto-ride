<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_pricing', function (Blueprint $table) {
            $table->id();
            $table->date('date');                          // Specific calendar date, e.g. 2026-04-14 (Khmer New Year)
            $table->string('label');                        // "Khmer New Year"
            $table->decimal('surcharge_rate', 4, 2);         // e.g. 0.30 = +30%, added on top of the fare like night surcharge
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_pricing');
    }
};
