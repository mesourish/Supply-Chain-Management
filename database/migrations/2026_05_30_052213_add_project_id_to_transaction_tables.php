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
            $table->foreignId('project_id')->nullable()->after('supplier_id')->constrained('projects')->nullOnDelete();
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('customer_id')->constrained('projects')->nullOnDelete();
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('sales_order_id')->constrained('projects')->nullOnDelete();
        });
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('purchase_order_id')->constrained('projects')->nullOnDelete();
        });
        Schema::table('return_requests', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
