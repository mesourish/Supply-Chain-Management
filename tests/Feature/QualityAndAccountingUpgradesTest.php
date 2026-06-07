<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodsReceiptNote;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\PaymentLog;
use App\Models\Shipment;
use App\Models\QualityCheck;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityAndAccountingUpgradesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard permissions, roles, and realistic objects
        $this->artisan('db:seed');
    }

    public function test_confirming_goods_receipt_creates_quality_check_and_balanced_ledger_entries()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // 1. Create supplier and product
        $supplier = Supplier::create([
            'name' => 'Ledger Supplier Ltd',
            'contact_person' => 'Bob Brown',
            'email' => 'bob@ledgersupplier.com',
            'phone' => '1122334455',
            'address' => '789 Accounting Blvd',
        ]);

        $product = Product::create([
            'sku' => 'PROD-GRN-LEDGER',
            'name' => 'Ledger Tested Product',
            'cost_price' => 100.00,
            'unit_price' => 150.00,
            'reorder_level' => 10,
            'product_type' => 'storable',
            'route' => 'buy',
            'min_stock' => 5,
            'max_stock' => 50,
        ]);

        $product->suppliers()->attach($supplier->id, ['price' => 95.00]);

        // 2. Create Purchase Order in approved status
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'approval_status' => 'approved',
            'currency_code' => 'USD',
            'exchange_rate' => 1.0,
            'total_amount' => 950.00,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 95.00,
        ]);

        // 3. Create GRN & AccountPayable (simulating receiving goods)
        $grn = GoodsReceiptNote::create([
            'purchase_order_id' => $po->id,
            'user_id' => $admin->id,
            'status' => 'received',
        ]);

        // Trigger AccountPayable creation manually (normally done in Livewire confirmReceipt)
        $ap = AccountPayable::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'amount' => 950.00,
            'status' => 'unpaid',
        ]);

        // Trigger QualityCheck creation manually (normally done in Livewire confirmReceipt)
        \App\Helpers\QualityControlHelper::createGrnCheck($grn, $product->id);

        // 4. Assert Quality Check was created in pending status
        $this->assertDatabaseHas('quality_checks', [
            'reference_type' => GoodsReceiptNote::class,
            'reference_id' => $grn->id,
            'product_id' => $product->id,
            'status' => 'pending',
        ]);

        // 5. Assert General Ledger postings were created (Debit Inventory / Credit AP)
        $this->assertDatabaseHas('journal_entries', [
            'reference_source' => 'AP-' . $ap->id,
            'description' => "Inventory asset receipt from Goods Receipt Note (PO-#{$po->id})",
        ]);

        $entry = JournalEntry::where('reference_source', 'AP-' . $ap->id)->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->isBalanced());

        // Assert Debit to Inventory Asset (code: 14000)
        $inventoryAccount = Account::where('code', '14000')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inventoryAccount->id,
            'debit_amount' => 950.00,
            'credit_amount' => 0.00,
        ]);

        // Assert Credit to Accounts Payable (code: 21000)
        $apAccount = Account::where('code', '21000')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $apAccount->id,
            'debit_amount' => 0.00,
            'credit_amount' => 950.00,
        ]);
    }

    public function test_invoice_creation_posts_balanced_ar_ledger_entries()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $customer = Customer::create([
            'name' => 'Ledger Customer LLC',
            'contact_person' => 'Alice Green',
            'email' => 'alice@green.com',
            'phone' => '123123123',
            'address' => '456 Client Rd',
        ]);

        // Create Invoice which triggers postInvoiceIssued
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'status' => 'issued',
            'amount' => 500.00,
        ]);

        // Assert General Ledger postings (Debit AR / Credit Revenue)
        $this->assertDatabaseHas('journal_entries', [
            'reference_source' => 'Invoice-' . $invoice->id,
        ]);

        $entry = JournalEntry::where('reference_source', 'Invoice-' . $invoice->id)->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->isBalanced());

        // Debit to Accounts Receivable (code: 12000)
        $arAccount = Account::where('code', '12000')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $arAccount->id,
            'debit_amount' => 500.00,
            'credit_amount' => 0.00,
        ]);

        // Credit to Sales Revenue (code: 41000)
        $revenueAccount = Account::where('code', '41000')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit_amount' => 0.00,
            'credit_amount' => 500.00,
        ]);
    }

    public function test_payments_log_posts_cash_ledger_entries()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $customer = Customer::create([
            'name' => 'Ledger Customer LLC',
            'contact_person' => 'Alice Green',
            'email' => 'alice@green.com',
            'phone' => '123123123',
            'address' => '456 Client Rd',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'status' => 'issued',
            'amount' => 500.00,
        ]);

        $receivable = AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => 500.00,
            'status' => 'pending',
        ]);

        // Create PaymentLog (Customer pays cash)
        $paymentLog = PaymentLog::create([
            'account_receivable_id' => $receivable->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
        ]);

        // Assert General Ledger postings (Debit Cash / Credit AR)
        $this->assertDatabaseHas('journal_entries', [
            'reference_source' => 'PaymentLog-' . $paymentLog->id,
        ]);

        $entry = JournalEntry::where('reference_source', 'PaymentLog-' . $paymentLog->id)->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->isBalanced());

        // Debit to Cash (code: 10100)
        $cashAccount = Account::where('code', '10100')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit_amount' => 500.00,
        ]);

        // Credit to Accounts Receivable (code: 12000)
        $arAccount = Account::where('code', '12000')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $arAccount->id,
            'credit_amount' => 500.00,
        ]);
    }

    public function test_delivering_shipment_calculates_and_posts_cogs()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Create product
        $product = Product::create([
            'sku' => 'PROD-SHIP-COGS',
            'name' => 'Shipment COGS Product',
            'cost_price' => 120.00,
            'unit_price' => 200.00,
            'reorder_level' => 10,
            'product_type' => 'storable',
            'route' => 'buy',
            'min_stock' => 5,
            'max_stock' => 50,
        ]);

        $customer = Customer::create([
            'name' => 'Alice Green',
            'email' => 'alice@green.com',
            'phone' => '123123123',
        ]);

        // Create Sales Order
        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
            'total_amount' => 600.00,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 200.00,
        ]);

        // Create Shipment in transit status
        $shipment = Shipment::create([
            'sales_order_id' => $so->id,
            'status' => 'in_transit',
        ]);

        // Deliver Shipment (triggers postShipmentDelivered)
        $shipment->update(['status' => 'delivered']);

        // Cost = 3 units * $120.00 cost price = $360.00
        $this->assertDatabaseHas('journal_entries', [
            'reference_source' => 'ShipmentDelivered-' . $shipment->id,
        ]);

        $entry = JournalEntry::where('reference_source', 'ShipmentDelivered-' . $shipment->id)->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->isBalanced());

        // Debit to COGS (code: 51000)
        $cogsAccount = Account::where('code', '51000')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cogsAccount->id,
            'debit_amount' => 360.00,
        ]);

        // Credit to Inventory Asset (code: 14000)
        $inventoryAccount = Account::where('code', '14000')->first();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inventoryAccount->id,
            'credit_amount' => 360.00,
        ]);
    }
}
