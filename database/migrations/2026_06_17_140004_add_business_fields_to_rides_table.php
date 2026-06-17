<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->foreignId('business_account_id')->nullable()->constrained()->nullOnDelete()->after('airport_zone_id');
            $table->boolean('is_business_trip')->default(false)->after('business_account_id');
            $table->string('expense_category', 60)->nullable()->after('is_business_trip');
            $table->string('expense_ref', 60)->nullable()->after('expense_category');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['business_account_id']);
            $table->dropColumn(['business_account_id', 'is_business_trip', 'expense_category', 'expense_ref']);
        });
    }
};
