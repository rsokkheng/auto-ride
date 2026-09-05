<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surge_zones', function (Blueprint $table) {
            $table->string('name_en', 255)->nullable();
            $table->string('name_kh', 255)->nullable();
            $table->string('name_zh', 255)->nullable();
        });

        DB::table('surge_zones')->whereNull('name_en')->update(['name_en' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('surge_zones', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_kh', 'name_zh']);
        });
    }
};
