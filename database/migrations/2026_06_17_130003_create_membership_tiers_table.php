<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 30)->unique();
            $table->unsignedInteger('min_points')->default(0);
            $table->string('badge_color', 20)->default('#94a3b8');
            $table->string('icon', 60)->default('fas fa-star');
            $table->json('benefits')->nullable();
            $table->unsignedTinyInteger('ride_discount_pct')->default(0);
            $table->unsignedTinyInteger('delivery_discount_pct')->default(0);
            $table->unsignedTinyInteger('points_multiplier')->default(1);
            $table->boolean('priority_support')->default(false);
            $table->boolean('free_cancellations')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default tiers
        DB::table('membership_tiers')->insert([
            ['name'=>'Bronze',   'slug'=>'bronze',   'min_points'=>0,     'badge_color'=>'#cd7f32','icon'=>'fas fa-medal',  'benefits'=>json_encode(['Earn 1× points on every trip']),                                              'ride_discount_pct'=>0, 'delivery_discount_pct'=>0, 'points_multiplier'=>1, 'priority_support'=>0,'free_cancellations'=>0,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Silver',   'slug'=>'silver',   'min_points'=>1000,  'badge_color'=>'#94a3b8','icon'=>'fas fa-medal',  'benefits'=>json_encode(['Earn 1.5× points','5% ride discount']),                                      'ride_discount_pct'=>5, 'delivery_discount_pct'=>0, 'points_multiplier'=>2, 'priority_support'=>0,'free_cancellations'=>0,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Gold',     'slug'=>'gold',     'min_points'=>5000,  'badge_color'=>'#f59e0b','icon'=>'fas fa-trophy', 'benefits'=>json_encode(['Earn 2× points','10% ride discount','5% delivery discount']),               'ride_discount_pct'=>10,'delivery_discount_pct'=>5,'points_multiplier'=>2, 'priority_support'=>0,'free_cancellations'=>0,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Platinum', 'slug'=>'platinum', 'min_points'=>15000, 'badge_color'=>'#6366f1','icon'=>'fas fa-crown',  'benefits'=>json_encode(['Earn 3× points','15% ride discount','10% delivery discount','Priority support','Free cancellations']), 'ride_discount_pct'=>15,'delivery_discount_pct'=>10,'points_multiplier'=>3,'priority_support'=>1,'free_cancellations'=>1,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_tiers');
    }
};
