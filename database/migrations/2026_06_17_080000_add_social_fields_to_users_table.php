<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('social_provider', 20)->nullable()->after('fcm_token');
            $table->string('social_id', 128)->nullable()->after('social_provider');
            $table->index(['social_provider', 'social_id'], 'users_social_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_social_idx');
            $table->dropColumn(['social_provider', 'social_id']);
        });
    }
};
