<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Driver vehicle ownership type — only relevant when role = 'driver'.
            // owner        : driver owns their own car / tuk-tuk
            // company_staff: employed by a company; vehicle belongs to company
            // rental       : rents a car / tuk-tuk from a company
            $table->string('driver_type', 32)->nullable()->after('role');

            // Company name for company_staff and rental types.
            $table->string('company_name', 255)->nullable()->after('driver_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['driver_type', 'company_name']);
        });
    }
};
