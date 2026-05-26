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
        Schema::table('account_payables', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('status');
        });
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
