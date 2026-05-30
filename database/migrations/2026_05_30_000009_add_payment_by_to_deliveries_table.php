<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // 'sender'    = sender pays upfront
            // 'recipient' = recipient pays on delivery (Cash on Delivery / COD)
            $table->string('payment_by', 16)->default('sender')->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('payment_by');
        });
    }
};
