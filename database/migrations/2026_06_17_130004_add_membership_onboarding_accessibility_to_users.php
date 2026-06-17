<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('membership_tier_id')->nullable()->constrained('membership_tiers')->nullOnDelete()->after('loyalty_points');
            $table->timestamp('onboarding_completed_at')->nullable()->after('membership_tier_id');
            $table->json('onboarding_steps')->nullable()->after('onboarding_completed_at');
            $table->json('accessibility_settings')->nullable()->after('onboarding_steps');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['membership_tier_id']);
            $table->dropColumn(['membership_tier_id', 'onboarding_completed_at', 'onboarding_steps', 'accessibility_settings']);
        });
    }
};
