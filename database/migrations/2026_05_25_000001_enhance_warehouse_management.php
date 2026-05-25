<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance warehouse_bins with zone/aisle/rack/shelf details
        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->string('zone')->nullable()->after('bin_code');       // e.g. A, B, C
            $table->string('aisle')->nullable()->after('zone');          // e.g. 01, 02
            $table->string('rack')->nullable()->after('aisle');          // e.g. R1, R2
            $table->string('shelf')->nullable()->after('rack');          // e.g. S1, S2, S3
            $table->string('bin_type')->default('standard')->after('shelf'); // standard, bulk, cold, hazmat
            $table->decimal('max_weight_kg', 10, 2)->nullable()->after('bin_type');
            $table->decimal('max_volume_m3', 10, 4)->nullable()->after('max_weight_kg');
            $table->boolean('is_active')->default(true)->after('max_volume_m3');
            $table->text('notes')->nullable()->after('is_active');
        });

        // 2. Add bin_product_stock — tracks current quantity of each product in each bin
        Schema::create('bin_product_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_bin_id')->constrained('warehouse_bins')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_cost', 12, 4)->nullable(); // for FIFO/weighted avg costing
            $table->timestamps();
            $table->unique(['warehouse_bin_id', 'product_id']);
        });

        // 3. Stock Takes (Physical Inventory Count sessions)
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->enum('status', ['draft', 'in_progress', 'pending_approval', 'approved', 'cancelled'])->default('draft');
            $table->string('scope')->default('full'); // full, partial, zone, product_category
            $table->string('scope_filter')->nullable(); // zone letter or category name if partial
            $table->text('notes')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Stock Take Items — line items for each bin+product counted
        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained('stock_takes')->cascadeOnDelete();
            $table->foreignId('warehouse_bin_id')->constrained('warehouse_bins');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('system_quantity')->default(0);   // quantity from system before count
            $table->integer('counted_quantity')->nullable();   // actual physical count
            $table->integer('variance')->virtualAs('COALESCE(counted_quantity, 0) - system_quantity');
            $table->enum('status', ['pending', 'counted', 'recounted'])->default('pending');
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Bin Transfers — track inter-bin stock moves
        Schema::create('bin_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('from_bin_id')->constrained('warehouse_bins');
            $table->foreignId('to_bin_id')->constrained('warehouse_bins');
            $table->integer('quantity');
            $table->string('reason')->nullable();
            $table->foreignId('transferred_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_transfers');
        Schema::dropIfExists('stock_take_items');
        Schema::dropIfExists('stock_takes');
        Schema::dropIfExists('bin_product_stock');
        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->dropColumn(['zone', 'aisle', 'rack', 'shelf', 'bin_type', 'max_weight_kg', 'max_volume_m3', 'is_active', 'notes']);
        });
    }
};
