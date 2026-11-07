<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partner Package Delivery: a passenger account registers as a Partner
 * (approved by ROTEH), then sends packages from the same Passenger app.
 * Packages are rows in `deliveries` (partner_id set) so drivers, live
 * tracking, payments and receipts work unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_profiles')) {
            Schema::create('partner_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('business_name');
                $table->string('business_type', 50)->nullable();
                $table->string('contact_name')->nullable();
                $table->string('phone', 24);
                $table->string('address', 500)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('status', 20)->default('pending'); // pending | approved | rejected | suspended
                $table->string('rejection_reason', 500)->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index('status');
            });
        }

        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'package_type'))   $table->string('package_type', 50)->nullable()->after('package_size');
            if (! Schema::hasColumn('deliveries', 'package_weight')) $table->decimal('package_weight', 8, 2)->nullable()->after('package_type');
            if (! Schema::hasColumn('deliveries', 'package_photo'))  $table->string('package_photo')->nullable()->after('package_weight');
            if (! Schema::hasColumn('deliveries', 'confirmed_at'))   $table->timestamp('confirmed_at')->nullable()->after('assigned_at');
        });

        if (! Schema::hasTable('package_tracking_histories')) {
            Schema::create('package_tracking_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
                $table->string('status', 30);       // partner-facing status
                $table->string('raw_status', 30);   // deliveries.status at that moment
                $table->string('note', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['delivery_id', 'id']);
            });
        }

        // Accounts the admin already made Partners (web portal) count as approved.
        $now = now();
        DB::table('users')->where('role', 'partner')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('partner_profiles')->whereColumn('partner_profiles.user_id', 'users.id'))
            ->orderBy('id')
            ->each(function ($u) use ($now) {
                DB::table('partner_profiles')->insert([
                    'user_id' => $u->id, 'business_name' => $u->company_name ?: $u->name,
                    'phone' => $u->phone ?? '', 'status' => 'approved', 'reviewed_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_tracking_histories');
        Schema::table('deliveries', function (Blueprint $table) {
            foreach (['package_type', 'package_weight', 'package_photo', 'confirmed_at'] as $c) {
                if (Schema::hasColumn('deliveries', $c)) $table->dropColumn($c);
            }
        });
        Schema::dropIfExists('partner_profiles');
    }
};
