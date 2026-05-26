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
        // 1. CRM Leads Table
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('title');
            $table->string('company_name')->nullable();
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->decimal('deal_value', 15, 2)->default(0.00);
            $table->string('pipeline_stage')->default('new'); // new, contacted, proposal, negotiation, won, lost
            $table->integer('deal_probability')->default(10); // in percent e.g. 10, 50, 90
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. CRM Activities Table
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->onDelete('cascade');
            $table->string('type'); // call, email, meeting, note
            $table->text('description');
            $table->date('activity_date');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 3. Sales Quotations Table
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('crm_lead_id')->nullable()->constrained('crm_leads')->onDelete('set null');
            $table->string('status')->default('draft'); // draft, sent, accepted, rejected, expired
            $table->date('valid_until');
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('shipping_amount', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Sales Quotation Items Table
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->timestamps();
        });

        // 5. Procurement RFQs Table (Request for Quotations from suppliers)
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->string('status')->default('draft'); // draft, sent, received, accepted, closed
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Projects Table
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('status')->default('planning'); // planning, active, completed, on_hold, cancelled
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Project Milestones Table
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('status')->default('pending'); // pending, completed
            $table->timestamps();
        });

        // 8. Project Material Requests / Inventory stock reservations
        Schema::create('project_material_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('warehouse_bin_id')->nullable()->constrained('warehouse_bins')->onDelete('set null');
            $table->decimal('quantity_requested', 15, 2);
            $table->decimal('quantity_reserved', 15, 2)->default(0.00);
            $table->string('status')->default('pending'); // pending, reserved, issued, returned
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_material_requests');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_leads');
    }
};
