<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pricing_settings')->updateOrInsert(
            ['key' => 'driver_min_balance_khr'],
            [
                'value'       => '50000',
                'label'       => 'Driver Minimum Wallet Balance (KHR)',
                'description' => 'Minimum wallet balance required for a driver to go online and request withdrawals.',
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('pricing_settings')->where('key', 'driver_min_balance_khr')->delete();
    }
};
