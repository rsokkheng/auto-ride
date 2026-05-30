<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('current_latitude', 10, 7)->nullable()->after('status_note');
            $table->decimal('current_longitude', 10, 7)->nullable()->after('current_latitude');
            $table->decimal('rating', 3, 2)->default(5.00)->after('current_longitude');
            $table->unsignedInteger('total_ratings')->default(0)->after('rating');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('rating', 2, 1)->nullable()->after('notes');
            $table->text('rating_comment')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_comment']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['current_latitude', 'current_longitude', 'rating', 'total_ratings']);
        });
    }
};
