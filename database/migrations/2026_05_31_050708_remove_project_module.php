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
        Schema::disableForeignKeyConstraints();

        $tables = [
            'quotations',
            'sales_orders',
            'purchase_orders',
            'invoices',
            'goods_receipt_notes',
            'return_requests',
            'expenses',
            'shipments'
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'project_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['project_id']);
                    $table->dropColumn('project_id');
                });
            }
        }

        Schema::dropIfExists('project_material_requests');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversing this is complex because we'd have to recreate the projects tables
        // and re-add the columns. Since this is a deliberate module removal, we can leave down empty
        // or just re-add the columns as nullable without foreign constraints to prevent complete failure.
    }
};
