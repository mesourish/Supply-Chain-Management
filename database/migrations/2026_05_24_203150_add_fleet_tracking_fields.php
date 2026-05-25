<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('status')->default('available'); // available, maintenance, active
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('capacity')->nullable();
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('status')->default('offline'); // offline, online, on_job
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignId('current_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->text('origin_address')->nullable();
            $table->text('destination_address')->nullable();
            $table->decimal('origin_lat', 10, 8)->nullable();
            $table->decimal('origin_lng', 11, 8)->nullable();
            $table->decimal('dest_lat', 10, 8)->nullable();
            $table->decimal('dest_lng', 11, 8)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['status', 'latitude', 'longitude', 'capacity']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['current_vehicle_id']);
            $table->dropColumn(['status', 'latitude', 'longitude', 'current_vehicle_id']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'origin_address', 'destination_address', 
                'origin_lat', 'origin_lng', 
                'dest_lat', 'dest_lng'
            ]);
        });
    }
};
