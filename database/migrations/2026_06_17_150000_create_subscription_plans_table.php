<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 40)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price_khr');
            $table->enum('billing_cycle', ['weekly', 'monthly', 'yearly'])->default('monthly');
            $table->unsignedInteger('ride_credit_khr')->default(0);
            $table->unsignedTinyInteger('ride_discount_pct')->default(0);
            $table->unsignedTinyInteger('delivery_discount_pct')->default(0);
            $table->unsignedSmallInteger('free_cancellations')->default(0);
            $table->boolean('surge_waived')->default(false);
            $table->boolean('priority_matching')->default(false);
            $table->unsignedTinyInteger('bonus_points_pct')->default(0);
            $table->json('features')->nullable();
            $table->string('badge_color', 20)->default('#6366f1');
            $table->string('icon', 60)->default('fas fa-star');
            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('subscription_plans')->insert([
            [
                'name'                  => 'Basic',
                'slug'                  => 'basic',
                'description'           => 'Get started with essential ride benefits.',
                'price_khr'             => 9900,
                'billing_cycle'         => 'monthly',
                'ride_credit_khr'       => 0,
                'ride_discount_pct'     => 5,
                'delivery_discount_pct' => 0,
                'free_cancellations'    => 3,
                'surge_waived'          => false,
                'priority_matching'     => false,
                'bonus_points_pct'      => 10,
                'features'              => json_encode(['5% off all rides', '3 free cancellations/month', '10% bonus loyalty points']),
                'badge_color'           => '#64748b',
                'icon'                  => 'fas fa-star',
                'active'                => true,
                'sort_order'            => 1,
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'name'                  => 'Plus',
                'slug'                  => 'plus',
                'description'           => 'More savings with ride credit and priority matching.',
                'price_khr'             => 24900,
                'billing_cycle'         => 'monthly',
                'ride_credit_khr'       => 20000,
                'ride_discount_pct'     => 10,
                'delivery_discount_pct' => 5,
                'free_cancellations'    => 10,
                'surge_waived'          => false,
                'priority_matching'     => true,
                'bonus_points_pct'      => 25,
                'features'              => json_encode(['20,000 ៛ ride credit/month', '10% off rides', '5% off deliveries', 'Priority driver matching', '10 free cancellations', '25% bonus loyalty points']),
                'badge_color'           => '#3b82f6',
                'icon'                  => 'fas fa-bolt',
                'active'                => true,
                'sort_order'            => 2,
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'name'                  => 'Premium',
                'slug'                  => 'premium',
                'description'           => 'The ultimate experience — no surge, max savings.',
                'price_khr'             => 49900,
                'billing_cycle'         => 'monthly',
                'ride_credit_khr'       => 50000,
                'ride_discount_pct'     => 15,
                'delivery_discount_pct' => 10,
                'free_cancellations'    => 0,
                'surge_waived'          => true,
                'priority_matching'     => true,
                'bonus_points_pct'      => 50,
                'features'              => json_encode(['50,000 ៛ ride credit/month', '15% off rides', '10% off deliveries', 'Surge pricing waived', 'Priority driver matching', 'Unlimited cancellations', '50% bonus loyalty points']),
                'badge_color'           => '#f59e0b',
                'icon'                  => 'fas fa-crown',
                'active'                => true,
                'sort_order'            => 3,
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
