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
        Schema::table('products', function (Blueprint $table) {
            $table->enum('product_type', ['storable', 'consumable', 'service'])->default('storable')->after('category');
            $table->enum('route', ['manufacture', 'buy', 'buy_and_sell', 'make_to_order', 'drop_ship', 'service'])->default('buy')->after('product_type');
            $table->decimal('min_stock', 15, 4)->default(0.0000)->after('reorder_level');
            $table->decimal('max_stock', 15, 4)->default(0.0000)->after('min_stock');
            $table->string('image_path')->nullable()->after('description');

            // High Workload Optimizations: Add indexes on columns queried frequently by workflow observers
            $table->index('product_type');
            $table->index('route');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_type']);
            $table->dropIndex(['route']);
            $table->dropColumn(['product_type', 'route', 'min_stock', 'max_stock', 'image_path']);
        });
    }
};
