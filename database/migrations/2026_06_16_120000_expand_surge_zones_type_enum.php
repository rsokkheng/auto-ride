<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE surge_zones MODIFY COLUMN type ENUM('rides','deliveries','delivery','moving','both') NOT NULL DEFAULT 'both'");
    }

    public function down(): void
    {
        // Convert new specific types back to 'deliveries' before shrinking enum
        DB::statement("UPDATE surge_zones SET type = 'deliveries' WHERE type IN ('delivery','moving')");
        DB::statement("ALTER TABLE surge_zones MODIFY COLUMN type ENUM('rides','deliveries','both') NOT NULL DEFAULT 'both'");
    }
};
