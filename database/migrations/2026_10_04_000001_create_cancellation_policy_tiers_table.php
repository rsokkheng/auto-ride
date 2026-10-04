<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_policy_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 20);                       // before_accept | after_accept | after_arrival
            $table->unsignedInteger('from_minute')->default(0); // minutes since the stage started
            $table->unsignedInteger('fee_khr')->default(0);
            $table->timestamps();

            $table->unique(['stage', 'from_minute']);
        });

        // Carry over the flat fee settings this table replaces.
        $setting = fn (string $key, int $default) =>
            (int) (DB::table('pricing_settings')->where('key', $key)->value('value') ?? $default);

        $freeMinutes = $setting('cancel_free_minutes', 3);
        $now         = now();
        $rows        = [['before_accept', 0, 0]];
        if ($freeMinutes > 0) {
            $rows[] = ['after_accept', 0, 0];
        }
        $rows[] = ['after_accept', $freeMinutes, $setting('cancel_fee_after_accepted', 1000)];
        $rows[] = ['after_arrival', 0, $setting('cancel_fee_after_arrival', 3000)];

        DB::table('cancellation_policy_tiers')->insert(array_map(fn ($r) => [
            'stage'       => $r[0],
            'from_minute' => $r[1],
            'fee_khr'     => $r[2],
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_policy_tiers');
    }
};
