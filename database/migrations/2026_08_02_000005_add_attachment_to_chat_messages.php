<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // text | image | file
            $table->string('type')->default('text')->after('message');
            $table->string('attachment_url')->nullable()->after('type');
            $table->string('attachment_name')->nullable()->after('attachment_url');
            // message is now nullable (image-only messages have no text)
            $table->text('message')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'attachment_url', 'attachment_name']);
            $table->text('message')->nullable(false)->change();
        });
    }
};
