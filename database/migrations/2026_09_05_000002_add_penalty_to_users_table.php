<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('penalty_until')->nullable()->after('cancellation_penalty_until');
            $table->string('penalty_reason', 255)->nullable()->after('penalty_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['penalty_until', 'penalty_reason']);
        });
    }
};
