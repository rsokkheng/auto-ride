<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One ABA PayWay KHQR payment. Paid only after PayWay's Check Transaction confirms it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payway_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tran_id', 20)->unique();
            $table->string('purpose', 20)->default('topup');      // topup | ride | delivery | marketplace | rental
            $table->nullableMorphs('payable');
            $table->unsignedBigInteger('amount');                  // KHR, whole riel
            $table->string('currency', 3)->default('KHR');
            $table->string('status', 16)->default('pending');      // pending | paid | failed | cancelled | expired | refunded
            $table->string('apv', 20)->nullable();
            $table->string('payment_type', 30)->nullable();        // ABA Pay | KHQR | …
            $table->string('bank_ref', 64)->nullable();
            $table->json('last_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payway_transactions');
    }
};
