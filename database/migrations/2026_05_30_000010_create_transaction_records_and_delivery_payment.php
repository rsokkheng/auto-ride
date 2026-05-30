<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprehensive payment transaction ledger.
 *
 * payment_method:
 *   cash          – physical cash, needs admin/driver confirmation
 *   wallet        – deducted from sender/passenger in-app wallet
 *   aba           – ABA Bank transfer / ABA PAY (Cambodia)
 *   wing          – Wing Money (Cambodia)
 *   other_online  – any other online gateway
 *
 * payment_status on deliveries:
 *   unpaid    – not yet paid
 *   pending   – cash / online awaiting confirmation
 *   paid      – payment confirmed / completed
 *   refunded  – payment reversed
 *
 * transaction_records.status:
 *   pending   – cash or online awaiting confirmation
 *   completed – payment confirmed, wallets updated
 *   cancelled – transaction void
 *   refunded  – reversal processed
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Add payment fields to deliveries ──────────────────────────────────
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('payment_method', 32)->default('cash')->after('payment_by');
            $table->string('payment_status', 16)->default('unpaid')->after('payment_method');
        });

        // ── Add payment_method to rides ───────────────────────────────────────
        Schema::table('rides', function (Blueprint $table) {
            $table->string('payment_method', 32)->default('cash')->after('fare');
            $table->string('payment_status', 16)->default('unpaid')->after('payment_method');
        });

        // ── Master transaction ledger ─────────────────────────────────────────
        Schema::create('transaction_records', function (Blueprint $table) {
            $table->id();

            // What triggered this transaction (Delivery, Ride, TopUpRequest, etc.)
            $table->nullableMorphs('reference');

            // Who paid and who received payment.
            $table->foreignId('payer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payee_id')->nullable()->constrained('users')->nullOnDelete();

            // Transaction classification.
            $table->string('type', 32);          // delivery_payment|ride_payment|top_up|withdrawal|refund|salary
            $table->string('payment_method', 32);// cash|wallet|aba|wing|other_online
            $table->string('payment_by', 16)->default('sender'); // sender|recipient (COD)

            // Amounts (KHR, integers).
            $table->unsignedBigInteger('gross_amount');          // total fare/fee
            $table->unsignedBigInteger('platform_fee')->default(0);
            $table->unsignedBigInteger('company_share')->default(0);
            $table->unsignedBigInteger('driver_earning')->default(0);

            $table->string('status', 16)->default('pending');   // pending|completed|cancelled|refunded
            $table->text('note')->nullable();

            // Admin/driver who confirmed this transaction.
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['payer_id', 'created_at']);
            $table->index(['payee_id', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_records');

        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status']);
        });
    }
};
