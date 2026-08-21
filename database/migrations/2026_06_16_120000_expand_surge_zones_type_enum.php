<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only raw DDL — sqlite (used for the local/CI test suite) has no MODIFY
        // COLUMN syntax and stores enum as a CHECK constraint instead; skip there since
        // no test data ever needs the 'delivery'/'moving' surge zone types.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE surge_zones MODIFY COLUMN type ENUM('rides','deliveries','delivery','moving','both') NOT NULL DEFAULT 'both'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Convert new specific types back to 'deliveries' before shrinking enum
        DB::statement("UPDATE surge_zones SET type = 'deliveries' WHERE type IN ('delivery','moving')");
        DB::statement("ALTER TABLE surge_zones MODIFY COLUMN type ENUM('rides','deliveries','both') NOT NULL DEFAULT 'both'");
    }
};
