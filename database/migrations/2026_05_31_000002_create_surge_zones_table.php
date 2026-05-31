<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surge_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lng', 10, 7);
            $table->decimal('radius_km', 8, 2);
            $table->decimal('multiplier', 4, 2)->default(1.50); // e.g. 1.5 = +50%
            $table->enum('type', ['rides', 'deliveries', 'both'])->default('both');
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable(); // null = always
            $table->timestamp('ends_at')->nullable();   // null = forever
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surge_zones');
    }
};
