<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('expense_ref');
            $table->foreignId('family_member_id')->nullable()->constrained('family_members')->nullOnDelete()->after('booked_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['booked_by_user_id']);
            $table->dropForeign(['family_member_id']);
            $table->dropColumn(['booked_by_user_id', 'family_member_id']);
        });
    }
};
