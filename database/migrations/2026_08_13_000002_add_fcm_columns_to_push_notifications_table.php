<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->string('fcm_token', 512)->nullable()->after('payload');
            $table->string('fcm_status', 64)->nullable()->after('fcm_token');  // SUCCESS / UNREGISTERED / error code
        });
    }

    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'fcm_status']);
        });
    }
};
