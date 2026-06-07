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
        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type'); // e.g. "App\Models\GoodsReceiptNote" or "App\Models\ManufacturingOrder"
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->enum('status', ['pending', 'passed', 'failed', 'quarantined'])->default('pending');
            $table->text('findings_notes')->nullable();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();

            // Poly/composite index and optimization for heavy workloads
            $table->index(['reference_type', 'reference_id']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_checks');
    }
};
