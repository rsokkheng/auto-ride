<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $settings = [
        ['key' => 'moving_base_fee',             'value' => '20000', 'label' => 'Base Fee',                 'description' => 'Flat base fee per moving job (KHR)'],
        ['key' => 'moving_truck_fee',            'value' => '20000', 'label' => 'Truck Fee',                'description' => 'Flat truck surcharge per job (KHR)'],
        ['key' => 'moving_distance_rate',        'value' => '4000',  'label' => 'Distance Rate (per km)',   'description' => 'KHR charged per kilometre of road distance'],
        ['key' => 'moving_helper_rate_normal',   'value' => '8000',  'label' => 'Helper Rate — Normal',     'description' => 'KHR per helper for normal_carry jobs'],
        ['key' => 'moving_helper_rate_heavy',    'value' => '16000', 'label' => 'Helper Rate — Heavy',      'description' => 'KHR per helper for heavy_carry jobs (fridge, sofa, etc.)'],
        ['key' => 'moving_no_elevator_mult',     'value' => '1.5',   'label' => 'No-Elevator Multiplier',  'description' => 'Floor fee multiplier when there is no elevator (e.g. 1.5 = ×1.5)'],
        ['key' => 'moving_floor_fee_tier_1',     'value' => '4000',  'label' => 'Floor Fee — Ground / F1', 'description' => 'KHR for 1st floor (ground level)'],
        ['key' => 'moving_floor_fee_tier_3',     'value' => '12000', 'label' => 'Floor Fee — F2–F3',       'description' => 'KHR for 2nd–3rd floor carry'],
        ['key' => 'moving_floor_fee_tier_6',     'value' => '20000', 'label' => 'Floor Fee — F4–F6',       'description' => 'KHR for 4th–6th floor carry'],
        ['key' => 'moving_floor_fee_tier_7plus', 'value' => '40000', 'label' => 'Floor Fee — F7+',         'description' => 'KHR for 7th floor and above'],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->settings as $s) {
            DB::table('pricing_settings')->updateOrInsert(
                ['key' => $s['key']],
                array_merge($s, ['created_at' => $now, 'updated_at' => $now]),
            );
        }
    }

    public function down(): void
    {
        DB::table('pricing_settings')
            ->whereIn('key', array_column($this->settings, 'key'))
            ->delete();
    }
};
