<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per admin recipient, so each admin has their own read state
        // and only receives what their permissions allow (see AdminNotifications).
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);        // PURCHASE_ORDER | RENTAL_ORDER | PAYMENT_SUCCESS | ORDER_CANCELLED
            $table->string('category', 16);    // purchase | rental | system — the bell dropdown tabs
            $table->string('order_kind', 32);  // marketplace_order | car_rental
            $table->unsignedBigInteger('order_id');
            $table->string('title');
            $table->string('message');
            $table->json('data')->nullable();  // reference, detail line, amount, customer
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['order_kind', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
