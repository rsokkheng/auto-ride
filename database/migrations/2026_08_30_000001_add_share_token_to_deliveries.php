<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Public tracking link token — mirrors rides.share_token.
            $table->string('share_token', 64)->nullable()->unique()->after('status');
            $table->boolean('share_active')->default(true)->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'share_active']);
        });
    }
};
