<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();

            $table->string('phone', 20)->index();

            $table->string('otp_hash');

            $table->timestamp('expires_at');

            $table->timestamp('verified_at')->nullable()->default(null);

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('last_sent_at')->nullable();

            $table->timestamps();

            $table->index([
                'phone',
                'expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_otps');
    }
};