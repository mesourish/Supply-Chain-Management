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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dateTime('expected_delivery')->nullable();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dateTime('next_service_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('expected_delivery');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('next_service_date');
        });
    }
};
