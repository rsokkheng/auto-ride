<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->string('name_en', 64)->nullable();
            $table->string('name_kh', 64)->nullable();
            $table->string('name_zh', 64)->nullable();
        });

        DB::table('vehicle_types')->whereNull('name_en')->update(['name_en' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_kh', 'name_zh']);
        });
    }
};
