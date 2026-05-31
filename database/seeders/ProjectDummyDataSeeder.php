<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Expense;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ProjectDummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure we have at least one Customer
        $customer = Customer::first();
        if (!$customer) {
            $customer = Customer::create([
                'name' => 'Acme Corp',
                'email' => 'acme@example.com',
                'phone' => '123-456-7890',
            ]);
        }

        // 2. Ensure we have at least one Supplier
        $supplier = Supplier::first();
        if (!$supplier) {
            $supplier = Supplier::create([
                'name' => 'Global Supplies Ltd',
                'email' => 'info@globalsupplies.com',
                'phone' => '098-765-4321',
                'is_active' => true,
            ]);
        }

        // 3. Create a Dummy Project
        $project = Project::create([
            'name' => 'Data Center Expansion Phase 1',
            'code' => 'PRJ-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'status' => 'active',
            'start_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(5)->format('Y-m-d'),
            'budget' => 150000.00,
            'description' => "This is a dummy project for testing financial, tracking, and operational flows.\nIncludes rack mounting, server acquisitions, and network mapping.",
        ]);

        // 4. Create dummy milestones (optional, but good for testing)
        if (method_exists($project, 'milestones')) {
            $project->milestones()->create([
                'title' => 'Initial Assessment',
                'description' => 'Site analysis and blueprinting.',
                'status' => 'completed',
                'due_date' => Carbon::now()->subDays(15)->format('Y-m-d'),
            ]);
            $project->milestones()->create([
                'title' => 'Hardware Procurement',
                'description' => 'Procure all necessary server racks.',
                'status' => 'pending',
                'due_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            ]);
        }

        // 5. Create a Purchase Order linked to the project
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'status' => 'approved',
            'subtotal' => 25000.00,
            'gst_type' => 'exclusive',
            'gst_percentage' => 10,
            'gst_amount' => 2500.00,
            'total_amount' => 27500.00,
            'remarks' => 'Server rack hardware',
            'terms_and_conditions' => 'Standard 30 days',
        ]);

        // Create an Expense directly logged against the PO/Project
        Expense::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'category' => 'Hardware',
            'amount' => 27500.00,
            'expense_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'reference_number' => 'EXP-' . strtoupper(Str::random(6)),
            'notes' => 'Server rack initial invoice payment',
        ]);

        // 6. Create a Sales Order linked to the project
        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'confirmed',
            'total_amount' => 60000.00,
        ]);

        // Create an Invoice logged against the SO/Project
        Invoice::create([
            'sales_order_id' => $so->id,
            'project_id' => $project->id,
            'status' => 'sent',
            'amount' => 60000.00,
        ]);

        $this->command->info("Dummy Data seeded for Project: " . $project->name);
    }
}
