<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_item_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('disk', 20)->default('public');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_item_images');
    }
};
