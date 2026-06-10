<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Ride for someone else
            $table->string('passenger_name')->nullable()->after('passenger_id');
            $table->string('passenger_phone', 24)->nullable()->after('passenger_name');
            // Cancellation fee
            $table->unsignedInteger('cancellation_fee')->default(0)->after('fare');
            $table->string('cancellation_reason')->nullable()->after('cancellation_fee');
            // Share trip
            $table->string('share_token', 64)->nullable()->unique()->after('status');
            $table->boolean('share_active')->default(false)->after('share_token');
            // Driver arrives timeout (driver_arrived_at already exists)
            $table->timestamp('pickup_timeout_at')->nullable()->after('driver_arrived_at');
            // Promo
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete()->after('fare');
            $table->unsignedInteger('discount_amount')->default(0)->after('promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'passenger_name','passenger_phone','cancellation_fee','cancellation_reason',
                'share_token','share_active','pickup_timeout_at',
                'promo_code_id','discount_amount',
            ]);
        });
    }
};
