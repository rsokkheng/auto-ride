<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Settings used by the daily Partner statements / package flow. */
return new class extends Migration
{
    private array $settings = [
        ['key' => 'partner_failed_fee_percent', 'value' => '0', 'label' => 'Partner Unsuccessful Delivery Fee (%)',
         'description' => 'Percent of the service fee charged to the partner when a delivery was attempted but not delivered (returned). 0 = free. Cancellations are never charged.'],
        ['key' => 'partner_packages_auto_confirm', 'value' => '0', 'label' => 'Auto-confirm Partner Packages',
         'description' => '1 = new partner packages go straight to drivers; 0 = ROTEH confirms each package first.'],
    ];

    public function up(): void
    {
        foreach ($this->settings as $s) {
            if (! DB::table('pricing_settings')->where('key', $s['key'])->exists()) {
                DB::table('pricing_settings')->insert($s + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pricing_settings')->whereIn('key', array_column($this->settings, 'key'))->delete();
    }
};
