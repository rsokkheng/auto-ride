<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE phone_otps MODIFY verified_at DATETIME NULL DEFAULT NULL');
        DB::statement('ALTER TABLE phone_otps MODIFY last_sent_at DATETIME NULL DEFAULT NULL');
        DB::statement('ALTER TABLE phone_otps MODIFY expires_at DATETIME NOT NULL');
    }

    public function down(): void
    {
        Schema::table('phone_otps', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->change();
            $table->timestamp('last_sent_at')->nullable()->change();
            $table->timestamp('expires_at')->change();
        });
    }
};
