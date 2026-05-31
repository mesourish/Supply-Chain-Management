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
        Schema::create('system_constants', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // e.g. product_category, expense_category, gst_percentage
            $table->string('name'); // e.g. Electronics, Office Supplies, 5%
            $table->string('value')->nullable(); // Optional specific value if name isn't enough
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_constants');
    }
};
