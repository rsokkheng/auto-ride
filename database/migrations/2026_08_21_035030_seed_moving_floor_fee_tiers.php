<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tiers = [
        ['max_floor' => 1,    'fee' => 4000],
        ['max_floor' => 3,    'fee' => 12000],
        ['max_floor' => 6,    'fee' => 20000],
        ['max_floor' => null, 'fee' => 40000],
    ];

    public function up(): void
    {
        if (DB::table('moving_floor_fee_tiers')->count() > 0) {
            return;
        }

        $now = now();
        foreach ($this->tiers as $tier) {
            DB::table('moving_floor_fee_tiers')->insert(array_merge($tier, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('moving_floor_fee_tiers')->truncate();
    }
};
