<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add bin maintenance fields to warehouse_bins
        Schema::table('warehouse_bins', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouse_bins', 'bin_status')) {
                $table->string('bin_status', 20)->default('ACTIVE')->after('is_active');
            }
            if (!Schema::hasColumn('warehouse_bins', 'pick_face_flag')) {
                $table->boolean('pick_face_flag')->default(false)->after('bin_status');
            }
            if (!Schema::hasColumn('warehouse_bins', 'bin_sequence_no')) {
                $table->unsignedInteger('bin_sequence_no')->nullable()->after('pick_face_flag');
            }
            if (!Schema::hasColumn('warehouse_bins', 'staging_zone_flag')) {
                $table->boolean('staging_zone_flag')->default(false)->after('bin_sequence_no');
            }
        });

        // Add contact/address fields to warehouses
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'contact_person_name')) {
                $table->string('contact_person_name', 100)->nullable()->after('location');
            }
            if (!Schema::hasColumn('warehouses', 'contact_number')) {
                $table->string('contact_number', 30)->nullable()->after('contact_person_name');
            }
            if (!Schema::hasColumn('warehouses', 'contact_email')) {
                $table->string('contact_email', 100)->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('warehouses', 'address')) {
                $table->text('address')->nullable()->after('contact_email');
            }
            if (!Schema::hasColumn('warehouses', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('address');
            }
            if (!Schema::hasColumn('warehouses', 'capacity')) {
                $table->unsignedInteger('capacity')->default(0)->after('is_active');
            }
        });

        // Migrate existing is_active → bin_status for existing bins
        \DB::statement("UPDATE warehouse_bins SET bin_status = CASE WHEN is_active = 1 THEN 'ACTIVE' ELSE 'INACTIVE' END WHERE bin_status = 'ACTIVE' OR bin_status IS NULL");
    }

    public function down(): void
    {
        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->dropColumn(['bin_status', 'pick_face_flag', 'bin_sequence_no', 'staging_zone_flag']);
        });
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['contact_person_name', 'contact_number', 'contact_email', 'address', 'is_active', 'capacity']);
        });
    }
};
