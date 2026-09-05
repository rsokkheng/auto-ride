<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_vehicle_sizes', function (Blueprint $table) {
            $table->string('label_en', 30)->nullable();
            $table->string('label_kh', 30)->nullable();
            $table->string('label_zh', 30)->nullable();
        });

        DB::table('marketplace_vehicle_sizes')->whereNull('label_en')->update(['label_en' => DB::raw('label')]);
    }

    public function down(): void
    {
        Schema::table('marketplace_vehicle_sizes', function (Blueprint $table) {
            $table->dropColumn(['label_en', 'label_kh', 'label_zh']);
        });
    }
};
