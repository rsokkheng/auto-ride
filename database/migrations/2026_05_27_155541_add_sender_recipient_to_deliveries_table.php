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
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->after('sender_id');
            $table->string('recipient_name')->nullable()->after('sender_name');
            $table->enum('package_size', ['small', 'medium', 'large'])->default('small')->after('recipient_name');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['sender_name', 'recipient_name', 'package_size']);
        });
    }
};
