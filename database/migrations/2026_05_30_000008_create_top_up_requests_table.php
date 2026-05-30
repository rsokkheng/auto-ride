<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('top_up_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedBigInteger('amount');          // KHR
            $table->string('method', 32);                  // cash|online|company_credit

            // pending → approved or rejected
            $table->string('status', 16)->default('pending');

            $table->text('note')->nullable();              // driver's note
            $table->text('admin_note')->nullable();        // reason for rejection etc.

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_requests');
    }
};
