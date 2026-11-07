<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner package money trail:
 *   delivery_collections — what the driver verified receiving at handover
 *                          (cash ៛ / cash $ / bank ៛ / bank $, mixed)
 *   driver_closings      — evening close: accountant ↔ staff driver
 *   partner_closings     — close: accountant ↔ partner (statement settled)
 *   daily_closings       — the day's process confirmed finished
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users');
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('business_date');
            $table->unsignedBigInteger('expected_khr')->default(0);
            $table->unsignedBigInteger('cash_khr')->default(0);
            $table->decimal('cash_usd', 12, 2)->default(0);
            $table->unsignedBigInteger('bank_khr')->default(0);
            $table->decimal('bank_usd', 12, 2)->default(0);
            $table->string('bank_name', 40)->nullable();
            $table->string('bank_ref', 100)->nullable();
            $table->unsignedInteger('khr_per_usd');
            $table->bigInteger('total_khr');
            $table->bigInteger('difference_khr')->default(0); // total − expected
            $table->string('note', 500)->nullable();
            $table->foreignId('driver_closing_id')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'business_date']);
            $table->index(['partner_id', 'business_date']);
        });

        Schema::create('driver_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users');
            $table->date('business_date');
            $table->unsignedInteger('packages_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);
            $table->unsignedBigInteger('expected_cash_khr')->default(0);
            $table->decimal('expected_cash_usd', 12, 2)->default(0);
            $table->unsignedBigInteger('expected_bank_khr')->default(0);
            $table->decimal('expected_bank_usd', 12, 2)->default(0);
            $table->unsignedBigInteger('received_cash_khr')->default(0);
            $table->decimal('received_cash_usd', 12, 2)->default(0);
            $table->boolean('bank_verified')->default(false);
            $table->unsignedInteger('khr_per_usd');
            $table->bigInteger('difference_khr')->default(0); // received cash − expected cash (in ៛)
            $table->string('note', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['driver_id', 'business_date']);
        });

        Schema::create('partner_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('users');
            $table->date('business_date');
            $table->unsignedInteger('successful_count')->default(0);
            $table->unsignedInteger('unsuccessful_count')->default(0);
            $table->unsignedBigInteger('cod_collected')->default(0);
            $table->unsignedBigInteger('delivery_fees')->default(0);
            $table->unsignedBigInteger('roteh_pays')->default(0);
            $table->unsignedBigInteger('partner_pays')->default(0);
            $table->bigInteger('net_to_partner')->default(0);
            $table->string('settle_method', 20)->nullable();   // cash | bank | wallet | carry_over
            $table->string('settle_ref', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['partner_id', 'business_date']);
        });

        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->unsignedInteger('drivers_closed')->default(0);
            $table->unsignedInteger('partners_closed')->default(0);
            $table->json('totals')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('partner_closings');
        Schema::dropIfExists('driver_closings');
        Schema::dropIfExists('delivery_collections');
    }
};
