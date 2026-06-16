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
        Schema::table('rides', function (Blueprint $table) {
            $table->unsignedTinyInteger('passenger_rating')->nullable()->after('rating');
            $table->string('passenger_rating_comment', 500)->nullable()->after('passenger_rating');
            $table->timestamp('passenger_rated_at')->nullable()->after('passenger_rating_comment');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['passenger_rating', 'passenger_rating_comment', 'passenger_rated_at']);
        });
    }
};
