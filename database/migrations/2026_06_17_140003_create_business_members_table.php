<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->string('department', 80)->nullable();
            $table->string('cost_center', 60)->nullable();
            $table->string('employee_id', 40)->nullable();
            $table->unsignedBigInteger('monthly_limit_khr')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->unique(['business_account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_members');
    }
};
