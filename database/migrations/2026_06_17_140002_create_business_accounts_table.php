<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 8)->unique();
            $table->string('tax_id', 50)->nullable();
            $table->string('industry', 60)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('billing_email', 150)->nullable();
            $table->enum('billing_cycle', ['weekly', 'monthly'])->default('monthly');
            $table->unsignedBigInteger('monthly_credit_limit_khr')->default(0);
            $table->unsignedBigInteger('used_credit_khr')->default(0);
            $table->string('logo_url', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_accounts');
    }
};
