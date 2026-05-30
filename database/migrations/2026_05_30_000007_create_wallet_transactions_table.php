<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every money movement in the system is recorded here.
 *
 * Transaction types:
 *   top_up              – driver deposits money (cash/online/company credit)
 *   trip_earning        – driver earned from a ride or delivery
 *   salary              – monthly salary credited by admin (employee drivers)
 *   platform_commission – platform fee deducted after trip
 *   company_commission  – company's cut deducted after trip (rental/employee)
 *   rental_fee          – daily vehicle rental fee deducted
 *   withdrawal          – driver withdraws cash
 *   bonus               – admin-issued bonus
 *   adjustment          – manual correction by admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // credit = money IN to wallet | debit = money OUT of wallet
            $table->string('direction', 6);  // credit | debit
            $table->string('type', 32);

            // Amount is always stored as a positive integer (KHR).
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');

            // What triggered this transaction.
            $table->nullableMorphs('reference');  // reference_id + reference_type

            $table->string('status', 16)->default('completed'); // completed|pending|cancelled
            $table->text('note')->nullable();

            // Admin who created/approved this transaction.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
