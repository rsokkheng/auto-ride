<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'cancelled', 'expired', 'paused'])->default('active');
            $table->string('payment_method', 40)->default('wallet');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->unsignedInteger('used_ride_credit_khr')->default(0);
            $table->unsignedSmallInteger('used_cancellations')->default(0);
            $table->unsignedSmallInteger('renewal_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
