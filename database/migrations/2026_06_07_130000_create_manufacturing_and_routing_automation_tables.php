<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Bill of Materials Recipes
        Schema::create('bills_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('bom_code')->unique();
            $table->string('name');
            $table->decimal('output_quantity', 15, 4)->default(1.0000);
            $table->timestamps();

            // Index optimized for heavy workload queries
            $table->index('product_id');
        });

        // 2. BOM Items / Component Raw Materials
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('bills_of_materials')->onDelete('cascade');
            $table->foreignId('component_product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity_required', 15, 4);
            $table->timestamps();

            // Indexes optimized for aggregation queries
            $table->index('bom_id');
            $table->index('component_product_id');
        });

        // 3. Work Centers
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('capacity_limit');
            $table->decimal('hourly_labor_rate', 10, 2);
            $table->timestamps();
        });

        // 4. Manufacturing Orders
        Schema::create('manufacturing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('mo_number')->unique();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('bom_id')->constrained('bills_of_materials');
            $table->decimal('quantity_to_produce', 15, 4);
            $table->enum('status', ['draft', 'confirmed', 'in_progress', 'quality_check', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->onDelete('set null');
            $table->date('scheduled_start_date');
            $table->date('actual_completed_date')->nullable();
            $table->timestamps();

            // Indexes optimized for workflow checks
            $table->index('product_id');
            $table->index('bom_id');
            $table->index('sales_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_orders');
        Schema::dropIfExists('work_centers');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('bills_of_materials');
    }
};
