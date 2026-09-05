<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_tiers', function (Blueprint $table) {
            $table->string('name_en', 60)->nullable();
            $table->string('name_kh', 60)->nullable();
            $table->string('name_zh', 60)->nullable();
        });

        DB::table('membership_tiers')->whereNull('name_en')->update(['name_en' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('membership_tiers', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_kh', 'name_zh']);
        });
    }
};
