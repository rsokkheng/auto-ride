<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_contracts', function (Blueprint $table) {
            $table->unsignedInteger('normal_fee')->default(5000)->after('partner_id')
                  ->comment('Flat fee for normal delivery (KHR)');
            $table->unsignedInteger('express_fee')->default(10000)->after('normal_fee')
                  ->comment('Flat fee for express delivery (KHR)');
            $table->unsignedInteger('surcharge_extra_large')->default(5000)->after('surcharge_large')
                  ->comment('Extra charge for extra large packages (KHR)');
        });

        // Remove old distance-based columns
        Schema::table('partner_contracts', function (Blueprint $table) {
            $table->dropColumn(['base_fee', 'per_km_rate', 'surcharge_small', 'surcharge_medium', 'min_fee']);
        });
    }

    public function down(): void
    {
        Schema::table('partner_contracts', function (Blueprint $table) {
            $table->unsignedInteger('base_fee')->default(3000)->after('partner_id');
            $table->unsignedInteger('per_km_rate')->default(1200)->after('base_fee');
            $table->unsignedInteger('surcharge_small')->default(0)->after('per_km_rate');
            $table->unsignedInteger('surcharge_medium')->default(2000)->after('surcharge_small');
            $table->unsignedInteger('min_fee')->default(3000)->after('surcharge_large');
        });

        Schema::table('partner_contracts', function (Blueprint $table) {
            $table->dropColumn(['normal_fee', 'express_fee', 'surcharge_extra_large']);
        });
    }
};
