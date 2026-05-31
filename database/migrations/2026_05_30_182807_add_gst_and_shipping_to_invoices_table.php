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
            $table->enum('gst_type', ['exclusive', 'inclusive'])->default('exclusive')->after('amount');
            $table->decimal('gst_percentage', 5, 2)->default(0)->after('gst_type');
            $table->decimal('shipping_amount', 15, 2)->default(0)->after('gst_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['gst_type', 'gst_percentage', 'shipping_amount']);
        });
    }
};
