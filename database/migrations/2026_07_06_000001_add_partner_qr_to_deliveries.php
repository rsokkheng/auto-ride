<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_id')->nullable()->after('sender_id');
            $table->string('qr_token', 64)->nullable()->unique()->after('partner_id');
            $table->timestamp('pickup_scanned_at')->nullable()->after('started_at');
            $table->timestamp('delivery_scanned_at')->nullable()->after('pickup_scanned_at');
            $table->string('assignment_type', 20)->nullable()->after('assigned_at'); // auto | manual

            $table->foreign('partner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropColumn(['partner_id', 'qr_token', 'pickup_scanned_at', 'delivery_scanned_at', 'assignment_type']);
        });
    }
};
