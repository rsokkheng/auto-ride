<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User profile photo (path relative to storage/app/public/).
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('phone');
        });

        // Vehicle photos stored as a JSON array of paths.
        // e.g. ["vehicles/3/abc123.jpg", "vehicles/3/def456.jpg"]
        // Max 5 images per vehicle, enforced in the controller.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('images')->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('images');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
