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
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->nullable()->change();
            $table->unsignedBigInteger('customer_id')->nullable()->after('sales_order_id');
            $table->text('description')->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->nullable(false)->change();
            $table->dropColumn(['customer_id', 'description']);
        });
    }
};
