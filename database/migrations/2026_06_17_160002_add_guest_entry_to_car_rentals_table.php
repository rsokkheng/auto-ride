<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_rentals', function (Blueprint $table) {
            // Make user_id nullable so guests can create rentals
            $table->foreignId('user_id')->nullable()->change();

            $table->enum('entry_type', ['user', 'guest'])->default('user')->after('user_id');
            $table->string('guest_name', 100)->nullable()->after('entry_type');
            $table->string('guest_phone', 20)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('car_rentals', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'guest_name', 'guest_phone']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
