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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('velocity', 10, 2)->default(0)->comment('Average daily consumption');
            $table->integer('lead_time_days')->default(7)->comment('Estimated days to restock');
            $table->integer('dynamic_reorder_level')->nullable()->comment('Calculated reorder threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['velocity', 'lead_time_days', 'dynamic_reorder_level']);
        });
    }
};
