<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airport_zones', function (Blueprint $table) {
            $table->string('name_en', 100)->nullable();
            $table->string('name_kh', 100)->nullable();
            $table->string('name_zh', 100)->nullable();
        });

        DB::table('airport_zones')->whereNull('name_en')->update(['name_en' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('airport_zones', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_kh', 'name_zh']);
        });
    }
};
