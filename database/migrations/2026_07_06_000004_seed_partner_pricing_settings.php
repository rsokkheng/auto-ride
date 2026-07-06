<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'partner_normal_fee',            'value' => '5000',  'label' => 'Partner Normal Delivery Fee',       'description' => 'Flat fee for normal partner delivery (KHR)'],
            ['key' => 'partner_express_fee',           'value' => '10000', 'label' => 'Partner Express Delivery Fee',      'description' => 'Flat fee for express partner delivery (KHR)'],
            ['key' => 'partner_surcharge_large',       'value' => '5000',  'label' => 'Partner Large Package Surcharge',   'description' => 'Extra charge for large packages on partner orders (KHR)'],
            ['key' => 'partner_surcharge_extra_large', 'value' => '5000',  'label' => 'Partner Extra Large Surcharge',     'description' => 'Extra charge for extra large packages on partner orders (KHR)'],
        ];

        foreach ($settings as $setting) {
            DB::table('pricing_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('pricing_settings')->whereIn('key', [
            'partner_normal_fee',
            'partner_express_fee',
            'partner_surcharge_large',
            'partner_surcharge_extra_large',
        ])->delete();
    }
};
