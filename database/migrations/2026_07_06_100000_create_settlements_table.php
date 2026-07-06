<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->enum('settlement_type', ['driver', 'partner']);
            $table->unsignedBigInteger('user_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'pending', 'approved', 'processing', 'paid', 'failed', 'cancelled'])->default('draft');

            // Driver metrics
            $table->unsignedInteger('rides_count')->default(0);
            $table->unsignedInteger('deliveries_count')->default(0);
            $table->bigInteger('gross_earnings')->default(0);
            $table->bigInteger('commission_total')->default(0);
            $table->bigInteger('tips_total')->default(0);
            $table->bigInteger('cod_collected')->default(0);

            // Partner metrics
            $table->unsignedInteger('orders_count')->default(0);
            $table->bigInteger('delivery_fees')->default(0);
            $table->bigInteger('cod_handled')->default(0);

            // Common financial
            $table->bigInteger('adjustments')->default(0);
            $table->string('adjustment_note')->nullable();
            $table->bigInteger('net_payout')->default(0);

            // Payment info
            $table->string('payment_method')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('payment_reference')->nullable();

            // Workflow
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
