<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A driver's "missed calls": ride / delivery / rental requests that were
 * offered to them but not accepted — either declined, or left until the
 * request timer ran out. One row per driver per request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_missed_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_type', 16);   // ride | delivery | rental
            $table->unsignedBigInteger('request_id');
            $table->string('reason', 16);         // declined | missed
            $table->json('summary')->nullable();  // pickup/dropoff/fare/customer snapshot
            $table->timestamps();

            $table->unique(['driver_id', 'request_type', 'request_id'], 'driver_missed_unique');
            $table->index(['driver_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_missed_requests');
    }
};
