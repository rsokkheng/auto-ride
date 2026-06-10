<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Driver approval workflow
            $table->enum('approval_status', ['pending','approved','rejected'])->default('pending')->after('role');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            // Driver cancellation limit & penalty
            $table->unsignedSmallInteger('cancellation_count')->default(0)->after('approved_at');
            $table->timestamp('cancellation_penalty_until')->nullable()->after('cancellation_count');
            // Masked phone proxy
            $table->string('proxy_phone', 24)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approval_status','approved_at','cancellation_count','cancellation_penalty_until','proxy_phone']);
        });
    }
};
