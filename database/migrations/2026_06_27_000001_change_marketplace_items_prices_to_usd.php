<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->default(null)->change();
            $table->decimal('rent_rate', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->default(0)->change();
            $table->unsignedBigInteger('rent_rate')->nullable()->default(null)->change();
        });
    }
};
