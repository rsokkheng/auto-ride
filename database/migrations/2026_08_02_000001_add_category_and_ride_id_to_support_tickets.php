<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('category')->nullable()->after('subject');
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete()->after('category');
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete()->after('ride_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['ride_id']);
            $table->dropForeign(['delivery_id']);
            $table->dropColumn(['category', 'ride_id', 'delivery_id']);
        });
    }
};
