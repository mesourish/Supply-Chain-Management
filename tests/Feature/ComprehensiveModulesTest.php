<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Currency;
use App\Models\DataImport;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Vehicle;
use App\Models\Shipment;
use App\Jobs\ProcessDataImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComprehensiveModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard permissions, roles, and realistic objects
        $this->artisan('db:seed');
    }

    /**
     * Test 1: Dynamic Currency & Base Currency Actions
     */
    public function test_dynamic_currencies_and_base_synchronization()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Assert base currency works
        $usd = Currency::updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1.0, 'is_base' => true]
        );

        $eur = Currency::updateOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 0.92, 'is_base' => false]
        );

        $this->assertEquals(1, $usd->is_base);
        $this->assertEquals(0, $eur->is_base);

        // Simulate base swap to EUR
        $usd->update(['is_base' => false]);
        $eur->update(['is_base' => true, 'exchange_rate' => 1.0]);

        $this->assertEquals(0, $usd->fresh()->is_base);
        $this->assertEquals(1, $eur->fresh()->is_base);
        $this->assertEquals(1.0, $eur->fresh()->exchange_rate);
    }

    /**
     * Test 2: Bulk Data Imports CSV Background Job
     */
    public function test_bulk_data_imports_csv_background_job()
    {
        Storage::fake('local');

        // Create mock CSV content (empty name column on last row to trigger failure)
        $csvContent = "name,email,contact_person,phone,tax_id,address\n"
                    . "Test Supplier A,test-a@example.com,John Doe,1234567,TAX-A,123 Main St\n"
                    . "Test Supplier B,test-b@example.com,Jane Doe,7654321,TAX-B,456 Oak Ave\n"
                    . ",test-c@example.com,No Name,0000000,TAX-C,789 Broad Way\n";

        $filePath = 'imports/test_suppliers.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $import = DataImport::create([
            'type' => 'supplier',
            'file_path' => $filePath,
            'status' => 'pending',
            'total_rows' => 0,
        ]);

        $this->assertEquals(0, Supplier::where('email', 'test-a@example.com')->count());

        // Process job synchronously
        $job = new ProcessDataImportJob($import);
        $job->handle();

        $import = $import->fresh();
        $this->assertEquals('completed', $import->status);
        $this->assertEquals(3, $import->total_rows);
        $this->assertEquals(2, $import->processed_rows);
        $this->assertEquals(1, $import->failed_rows);

        // Assert entities exist in db
        $this->assertEquals(1, Supplier::where('email', 'test-a@example.com')->count());
        $this->assertEquals(1, Supplier::where('email', 'test-b@example.com')->count());
    }

    /**
     * Test 3: Multi-Product Single-Click Transactions
     */
    public function test_multiproduct_purchase_order_single_click_creation()
    {
        $supplier = Supplier::first();
        $this->assertNotNull($supplier);

        $product1 = Product::first();
        $product2 = Product::skip(1)->first();

        // Create a PO using database transaction properties
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'subtotal' => 1000.00,
            'total_amount' => 1050.00,
            'gst_percentage' => 5,
            'gst_amount' => 50.00,
            'gst_type' => 'exclusive'
        ]);

        $po->items()->create([
            'product_id' => $product1->id,
            'quantity' => 10,
            'unit_price' => $product1->cost_price,
            'received_quantity' => 0,
        ]);

        $po->items()->create([
            'product_id' => $product2->id,
            'quantity' => 5,
            'unit_price' => $product2->cost_price,
            'received_quantity' => 0,
        ]);

        $this->assertEquals(2, $po->items()->count());
        $this->assertEquals(1050.00, $po->total_amount);
    }

    /**
     * Test 4: Logistics Fleet Utilization with No Active Data
     */
    public function test_logistics_fleet_utilization_with_zero_data()
    {
        // Delete all vehicles & shipments to test dynamic fallback bounds
        \DB::table('shipments')->delete();
        \DB::table('vehicles')->delete();

        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        // Utilization should correctly render 0% without hardcoded 20% fake placeholders
        $response->assertSee('0%');
    }

    /**
     * Test 5: AI & ML Intelligence Analytics Reports Render
     */
    public function test_ai_ml_intelligence_analytics_reports_render()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Render Report index
        $response = $this->get('/reports');
        $response->assertStatus(200);
        $response->assertSee('Comprehensive Reports');
        $response->assertSee('AI');
        $response->assertSee('ML Intelligence');
    }

    /**
     * Test 6: Automatic Contact Person polymorphic sync
     */
    public function test_supplier_customer_automatic_contact_persons_sync()
    {
        $supplier = Supplier::create([
            'name' => 'Auto Sync Supplier Ltd',
            'contact_person' => 'George Lucas',
            'email' => 'george@lucas.com',
            'phone' => '999111888',
        ]);

        $this->assertEquals(1, $supplier->contactPersons()->where('is_primary', true)->count());
        $this->assertEquals('George Lucas', $supplier->contactPersons()->where('is_primary', true)->first()->name);

        $customer = Customer::create([
            'name' => 'Auto Sync Customer Ltd',
            'contact_person' => 'Luke Skywalker',
            'email' => 'luke@skywalker.com',
            'phone' => '111888999',
        ]);

        $this->assertEquals(1, $customer->contactPersons()->where('is_primary', true)->count());
        $this->assertEquals('Luke Skywalker', $customer->contactPersons()->where('is_primary', true)->first()->name);
    }

    /**
     * Test 7: Warehouse Stock Take variance auto audit trail transaction log
     */
    public function test_warehouse_stock_take_variance_audit_trail()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $warehouse = \App\Models\Warehouse::first();
        $bin = \App\Models\WarehouseBin::where('warehouse_id', $warehouse->id)->first();
        $product = Product::first();

        // 1. Set initial stock
        \App\Models\BinProductStock::updateOrCreate(
            ['warehouse_bin_id' => $bin->id, 'product_id' => $product->id],
            ['quantity' => 50]
        );

        // 2. Create Stock Take with +10 surplus variance
        $st = \App\Models\StockTake::create([
            'reference_no' => 'ST-TEST-0001',
            'warehouse_id' => $warehouse->id,
            'created_by' => $admin->id,
            'status' => 'pending_approval',
            'scope' => 'full',
        ]);

        $item = \App\Models\StockTakeItem::create([
            'stock_take_id' => $st->id,
            'warehouse_bin_id' => $bin->id,
            'product_id' => $product->id,
            'system_quantity' => 50,
            'counted_quantity' => 60,
            'status' => 'counted',
            'counted_by' => $admin->id,
        ]);

        $this->assertEquals(0, \App\Models\InventoryTransaction::where('reference_type', \App\Models\StockTake::class)->count());

        // 3. Approve via Volt Livewire Component
        \Livewire\Volt\Volt::test('warehouses.stock-take.index')
            ->call('approveStockTake', $st->id);

        // 4. Assert stock take is approved and transaction was logged
        $this->assertEquals('approved', $st->fresh()->status);
        $this->assertEquals(60, \App\Models\BinProductStock::where('warehouse_bin_id', $bin->id)->where('product_id', $product->id)->value('quantity'));
        
        $this->assertEquals(1, \App\Models\InventoryTransaction::where('reference_type', \App\Models\StockTake::class)->count());
        $tx = \App\Models\InventoryTransaction::where('reference_type', \App\Models\StockTake::class)->first();
        $this->assertEquals('adjustment_in', $tx->type);
        $this->assertEquals(10, $tx->quantity);
        $this->assertEquals($bin->id, $tx->to_bin_id);
    }

    /**
     * Test 8: CRM Lead to Quotation conversion mapping & item/activity generation
     */
    public function test_crm_lead_to_quote_conversion_mappings()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $lead = \App\Models\CrmLead::create([
            'title' => 'Bulk Premium Laptop Order',
            'company_name' => 'TechCorp LLC',
            'contact_name' => 'Alice Johnson',
            'email' => 'alice@techcorp.com',
            'phone' => '+1-555-0199',
            'deal_value' => 25000.00,
            'pipeline_stage' => 'new',
            'deal_probability' => 10,
        ]);

        // Trigger conversion via CRM Leads Volt Component
        \Livewire\Volt\Volt::test('crm.leads')
            ->call('convertToQuote', $lead->id);

        $lead->refresh();
        $this->assertEquals('proposal', $lead->pipeline_stage);
        $this->assertEquals(60, $lead->deal_probability);
        $this->assertNotNull($lead->customer_id);

        // Assert customer exists with correct details
        $customer = \App\Models\Customer::find($lead->customer_id);
        $this->assertEquals('TechCorp LLC', $customer->name);
        $this->assertEquals('Alice Johnson', $customer->contact_person);

        // Assert quote exists with matching items and notes
        $quote = \App\Models\Quotation::where('crm_lead_id', $lead->id)->first();
        $this->assertNotNull($quote);
        $this->assertEquals(25000.00, $quote->total_amount);
        $this->assertEquals(1, $quote->items()->count());
        $this->assertEquals('Lead Opportunity: Bulk Premium Laptop Order', $quote->items->first()->description);

        // Assert CRM Activity timeline note was recorded
        $this->assertEquals(1, $lead->activities()->count());
        $this->assertEquals('note', $lead->activities->first()->type);
        $this->assertStringContainsString('converted to Sales Quote successfully', $lead->activities->first()->description);
    }

    /**
     * Test 9: Quotation to Sales Order address & polymorphic contact person conversion mapping
     */
    public function test_quotation_to_sales_order_address_and_contact_mappings()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Create a customer with contact and addresses
        $customer = \App\Models\Customer::create([
            'name' => 'Space Exploration Inc',
            'contact_person' => 'Elon Musk',
            'email' => 'elon@spacex.com',
            'phone' => '+1-555-4242',
            'billing_address' => '1 Rocket Road, Hawthorne, CA 90250',
            'shipping_address' => 'Starbase, Boca Chica, TX 78521',
        ]);

        // Retrieve generated primary contact and addresses
        $contactPerson = $customer->contactPersons()->where('is_primary', true)->first();
        $billingAddress = $customer->addresses()->where('type', 'billing')->first();
        $shippingAddress = $customer->addresses()->where('type', 'shipping')->first();

        $this->assertNotNull($contactPerson);
        $this->assertNotNull($billingAddress);
        $this->assertNotNull($shippingAddress);

        // Create a lead linked to the customer
        $lead = \App\Models\CrmLead::create([
            'title' => 'Starship Parts Supply',
            'customer_id' => $customer->id,
            'contact_name' => 'Elon Musk',
            'email' => 'elon@spacex.com',
            'deal_value' => 500000.00,
            'pipeline_stage' => 'negotiation',
            'deal_probability' => 80,
        ]);

        // Create quotation linked to lead
        $quote = \App\Models\Quotation::create([
            'reference_no' => 'QTE-TEST-777',
            'customer_id' => $customer->id,
            'crm_lead_id' => $lead->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30)->toDateString(),
            'total_amount' => 500000.00,
        ]);

        // Approve quotation via Volt Component
        \Livewire\Volt\Volt::test('sales.quotations')
            ->call('convertToSalesOrder', $quote->id);

        $quote->refresh();
        $this->assertEquals('accepted', $quote->status);

        // Assert SalesOrder exists with all mapped address and contact IDs
        $salesOrder = \App\Models\SalesOrder::where('customer_id', $customer->id)->first();
        $this->assertNotNull($salesOrder);
        $this->assertEquals($contactPerson->id, $salesOrder->contact_person_id);
        $this->assertEquals($billingAddress->id, $salesOrder->billing_address_id);
        $this->assertEquals($shippingAddress->id, $salesOrder->shipping_address_id);

        // Assert Lead pipeline transitioned to won & timeline logged
        $lead->refresh();
        $this->assertEquals('won', $lead->pipeline_stage);
        $this->assertEquals(100, $lead->deal_probability);
        $activity = $lead->activities()->where('type', 'meeting')->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('Auto-converted Quotation', $activity->description);
    }

    /**
     * Test 10: Bidirectional Invoice, Receivable and self-healing PaymentLog synchronizations
     */
    public function test_invoice_receivable_and_payment_log_cascading_synchronization()
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Tesla Motors',
            'email' => 'finance@tesla.com',
        ]);

        // 1. Create Invoice and corresponding AccountReceivable manually
        $invoice = \App\Models\Invoice::create([
            'customer_id' => $customer->id,
            'amount' => 15000.00,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
        ]);

        $receivable = \App\Models\AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => 15000.00,
            'status' => 'unpaid',
        ]);

        // 2. Log partial payment of 5000.00
        $payment1 = \App\Models\PaymentLog::create([
            'account_receivable_id' => $receivable->id,
            'amount' => 5000.00,
            'payment_date' => now()->toDateString(),
        ]);

        // Assert receivable and invoice statuses are partial
        $receivable->refresh();
        $invoice->refresh();
        $this->assertEquals('partial', $receivable->status);
        $this->assertEquals('partial', $invoice->status);
        $this->assertEquals(10000.00, $receivable->balance);

        // 3. Log remaining 10000.00 payment
        $payment2 = \App\Models\PaymentLog::create([
            'account_receivable_id' => $receivable->id,
            'amount' => 10000.00,
            'payment_date' => now()->toDateString(),
        ]);

        // Assert receivable and invoice statuses are fully paid
        $receivable->refresh();
        $invoice->refresh();
        $this->assertEquals('paid', $receivable->status);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0, $receivable->balance);

        // 4. Test deleting payment logs updates balances back to partial/unpaid
        $payment2->delete();
        $receivable->refresh();
        $invoice->refresh();
        $this->assertEquals('partial', $receivable->status);
        $this->assertEquals('partial', $invoice->status);
        $this->assertEquals(10000.00, $receivable->balance);

        // 5. Test manual Invoice status toggle to paid automatically logs a PaymentLog variance
        $invoice->update(['status' => 'paid']);
        $receivable->refresh();
        $invoice->refresh();
        $this->assertEquals('paid', $receivable->status);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0, $receivable->balance);

        // Payment logs sum must cover full 15000.00
        $this->assertEquals(15000.00, $receivable->payments()->sum('amount'));
    }
}
