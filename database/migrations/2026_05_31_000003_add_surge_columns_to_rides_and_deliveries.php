<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->decimal('surge_multiplier', 4, 2)->default(1.00)->after('fare');
            $table->foreignId('surge_zone_id')->nullable()->constrained('surge_zones')->nullOnDelete()->after('surge_multiplier');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('surge_multiplier', 4, 2)->default(1.00)->after('fee');
            $table->foreignId('surge_zone_id')->nullable()->constrained('surge_zones')->nullOnDelete()->after('surge_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['surge_zone_id']);
            $table->dropColumn(['surge_multiplier', 'surge_zone_id']);
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['surge_zone_id']);
            $table->dropColumn(['surge_multiplier', 'surge_zone_id']);
        });
    }
};
