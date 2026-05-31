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
            $table->decimal('subtotal', 15, 2)->after('status')->default(0);
            $table->enum('gst_type', ['inclusive', 'exclusive'])->after('subtotal')->default('exclusive');
            $table->decimal('gst_percentage', 5, 2)->after('gst_type')->default(0);
            $table->decimal('gst_amount', 15, 2)->after('gst_percentage')->default(0);
            // total_amount is already there, we will recalculate it
            
            $table->text('remarks')->nullable()->after('total_amount');
            $table->text('terms_and_conditions')->nullable()->after('remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal', 
                'gst_type', 
                'gst_percentage', 
                'gst_amount', 
                'remarks', 
                'terms_and_conditions'
            ]);
        });
    }
};
