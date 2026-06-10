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
        Schema::create('driver_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->unsignedInteger('target_trips');        // trips needed to earn bonus
            $table->unsignedInteger('bonus_amount');        // KHR
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->enum('status', ['active', 'earned', 'expired'])->default('active');
            $table->timestamp('earned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_incentives');
    }
};
