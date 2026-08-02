<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                    // card, aba, acleda, wing, payway, pi_pay
            $table->string('label')->nullable();       // "My ABA", "Visa ending 4242"
            $table->string('account_number')->nullable(); // masked: **** 4242
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('token')->nullable();       // provider token if applicable
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
