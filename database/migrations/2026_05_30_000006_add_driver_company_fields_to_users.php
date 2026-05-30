<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend users for the three driver types:
 *   employee  – salary-based, no per-trip wallet credit
 *   owner     – owns vehicle, pays platform commission per trip
 *   rental    – rents vehicle from company, pays platform + company commission
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // FK to companies — for employee and rental drivers.
            $table->foreignId('company_id')
                ->nullable()
                ->after('company_name')
                ->constrained('companies')
                ->nullOnDelete();

            // Monthly salary in KHR for employee-type drivers.
            $table->unsignedBigInteger('salary')->default(0)->after('company_id');

            // Per-driver platform commission override (%).
            // null = fall back to company rate, then config default.
            $table->decimal('commission_rate', 5, 2)->nullable()->after('salary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'salary', 'commission_rate']);
        });
    }
};
