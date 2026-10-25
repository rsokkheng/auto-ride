<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds {column}_en / _kh / _zh to every table whose text is shown in the app,
 * and backfills _en from the existing column. See App\Traits\HasLocalizedName.
 */
return new class extends Migration
{
    /** table => [base column => column type] */
    private const COLUMNS = [
        'banners'              => ['title' => 'string:100'],
        'holiday_pricing'      => ['label' => 'string'],
        'promo_codes'          => ['description' => 'string'],
        'promo_events'         => ['title' => 'string:100', 'body' => 'text'],
        'vouchers'             => ['title' => 'string', 'description' => 'text'],
        'charging_stations'    => ['name' => 'string', 'address' => 'string'],
        'vehicle_types'        => ['description' => 'string'],
        'surge_zones'          => ['description' => 'text'],
        'subscription_plans'   => ['description' => 'text', 'features' => 'json'],
        'membership_tiers'     => ['benefits' => 'json'],
        'marketplace_products' => ['title' => 'string', 'description' => 'text'],
        'marketplace_items'    => ['title' => 'string', 'description' => 'text'],
        'driver_incentives'    => ['title' => 'string', 'description' => 'string'],
        'push_notifications'   => ['title' => 'string', 'body' => 'text'],
    ];

    /** Tables that already have name_en/kh/zh but whose name_en may lag behind `name`. */
    private const NAME_RESYNC = ['vehicle_types', 'surge_zones', 'subscription_plans', 'membership_tiers', 'airport_zones'];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $base => $type) {
                    [$kind, $length] = array_pad(explode(':', $type), 2, null);
                    foreach (['en', 'kh', 'zh'] as $suffix) {
                        $col = match ($kind) {
                            'text'  => $t->text("{$base}_{$suffix}"),
                            'json'  => $t->json("{$base}_{$suffix}"),
                            default => $t->string("{$base}_{$suffix}", $length ? (int) $length : 255),
                        };
                        $col->nullable();
                    }
                }
            });

            foreach (array_keys($columns) as $base) {
                DB::table($table)->whereNull("{$base}_en")->update(["{$base}_en" => DB::raw($base)]);
            }
        }

        // Admin edits only ever wrote `name`, so name_en could be stale.
        foreach (self::NAME_RESYNC as $table) {
            DB::table($table)->whereNotNull('name')->update(['name_en' => DB::raw('name')]);
        }

        // Order accessory snapshots: name_km → name_kh, plus name_zh.
        Schema::table('marketplace_order_accessories', function (Blueprint $t) {
            $t->renameColumn('name_km', 'name_kh');
        });
        Schema::table('marketplace_order_accessories', function (Blueprint $t) {
            $t->string('name_zh')->nullable()->after('name_kh');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_order_accessories', function (Blueprint $t) {
            $t->dropColumn('name_zh');
        });
        Schema::table('marketplace_order_accessories', function (Blueprint $t) {
            $t->renameColumn('name_kh', 'name_km');
        });

        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach (array_keys($columns) as $base) {
                    $t->dropColumn(["{$base}_en", "{$base}_kh", "{$base}_zh"]);
                }
            });
        }
    }
};
