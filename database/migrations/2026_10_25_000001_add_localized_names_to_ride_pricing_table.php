<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NAMES = [
        'motorcycle' => ['ម៉ូតូ',           '摩托车'],
        'tuk_tuk'    => ['តុក តុក',         '嘟嘟车'],
        'standard'   => ['ឡានស្តង់ដារ',     '标准轿车'],
        'premium'    => ['ឡានពិសេស',        '高级轿车'],
        'shared'     => ['ជិះរួមគ្នា',        '拼车'],
        'van'        => ['ឡានវ៉ាន / XL',    '面包车 / XL'],
    ];

    public function up(): void
    {
        Schema::table('ride_pricing', function (Blueprint $table) {
            $table->string('name_en', 100)->nullable()->after('label');
            $table->string('name_kh', 100)->nullable()->after('name_en');
            $table->string('name_zh', 100)->nullable()->after('name_kh');
        });

        DB::table('ride_pricing')->whereNull('name_en')->update(['name_en' => DB::raw('label')]);

        foreach (self::NAMES as $serviceType => [$kh, $zh]) {
            DB::table('ride_pricing')
                ->where('service_type', $serviceType)
                ->update(['name_kh' => $kh, 'name_zh' => $zh]);
        }
    }

    public function down(): void
    {
        Schema::table('ride_pricing', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_kh', 'name_zh']);
        });
    }
};
