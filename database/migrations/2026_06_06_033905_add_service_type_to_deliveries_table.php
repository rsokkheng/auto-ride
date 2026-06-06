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
            // Service type: 'delivery' | 'moving'
            $table->string('service_type', 20)->default('delivery')->after('status');

            // Moving — building info
            $table->unsignedTinyInteger('floor_pickup')->nullable()->after('service_type');
            $table->unsignedTinyInteger('floor_dropoff')->nullable()->after('floor_pickup');
            $table->boolean('has_elevator')->default(false)->after('floor_dropoff');
            $table->boolean('needs_stairs_carry')->default(false)->after('has_elevator');
            $table->boolean('heavy_items')->default(false)->after('needs_stairs_carry');

            // Moving — helpers
            $table->unsignedTinyInteger('requires_helpers')->default(0)->after('heavy_items');
            $table->string('helper_type', 20)->nullable()->after('requires_helpers'); // normal_carry | heavy_carry

            // Moving — fee breakdown (KHR)
            $table->unsignedInteger('helper_fee')->nullable()->after('helper_type');
            $table->unsignedInteger('floor_fee')->nullable()->after('helper_fee');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'service_type',
                'floor_pickup', 'floor_dropoff',
                'has_elevator', 'needs_stairs_carry', 'heavy_items',
                'requires_helpers', 'helper_type',
                'helper_fee', 'floor_fee',
            ]);
        });
    }
};
