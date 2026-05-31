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
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('tax_amount', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['issue_date', 'due_date', 'tax_amount']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
};
