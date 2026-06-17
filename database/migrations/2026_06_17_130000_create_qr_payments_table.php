<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->unsignedInteger('amount_khr');
            $table->enum('status', ['pending', 'paid', 'expired', 'failed'])->default('pending');
            $table->enum('payment_type', ['topup', 'ride', 'delivery', 'marketplace'])->default('topup');
            $table->nullableMorphs('payable');
            $table->string('qr_data', 1024)->nullable();
            $table->string('bank_ref', 128)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_payments');
    }
};
