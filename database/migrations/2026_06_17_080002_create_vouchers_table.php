<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('category', ['rides', 'deliveries', 'all'])->default('all');
            $table->enum('discount_type', ['flat', 'percent'])->default('flat');
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('min_fare')->default(0);
            $table->unsignedInteger('max_discount')->default(0);
            $table->unsignedInteger('total_limit')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('points_required')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
