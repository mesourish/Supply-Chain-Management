<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\CrmLead;
use App\Models\CrmActivity;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\ManufacturingOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodsReceiptNote;
use App\Models\QualityCheck;
use App\Models\BinProductStock;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\PaymentLog;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\PredictiveMaintenance;
use App\Models\FuelAnomaly;
use App\Models\EquipmentUsageCharge;
use App\Models\FleetExpense;
use App\Models\VehicleDamageAudit;
use App\Models\FleetMarketplaceTransfer;
use App\Models\Project;
use App\Helpers\AccountingJournalHelper;

class RealLifeDataSeeder extends Seeder
{
    public function run(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        $now = Carbon::now();

        // ──────────────────────────────────────────────────────────────
        // 1. GENERAL LEDGER CHARTS OF ACCOUNTS
        // ──────────────────────────────────────────────────────────────
        AccountingJournalHelper::ensureAccountsExist();

        // ──────────────────────────────────────────────────────────────
        // 2. SYSTEM CONSTANTS
        // ──────────────────────────────────────────────────────────────
        DB::table('system_constants')->truncate();
        DB::table('system_constants')->insert([
            ['type' => 'product_category', 'name' => 'Raw Materials', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Electronics', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Machinery', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Server Hardware', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Logistics & Shipping', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Warehouse Management', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Equipment Lease', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '0% (Exempt)', 'value' => '0', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '5%', 'value' => '5', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '18%', 'value' => '18', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ──────────────────────────────────────────────────────────────
        // 3. CURRENCIES
        // ──────────────────────────────────────────────────────────────
        DB::table('currencies')->truncate();
        DB::table('currencies')->insert([
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1.0, 'is_base' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 0.92, 'is_base' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'exchange_rate' => 3.67, 'is_base' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ──────────────────────────────────────────────────────────────
        // 4. WAREHOUSES & BINS
        // ──────────────────────────────────────────────────────────────
        DB::table('warehouses')->truncate();
        DB::table('warehouse_bins')->truncate();

        $wh1 = Warehouse::create([
            'name' => 'Main Distribution Center (WH-01)',
            'location' => 'Jebel Ali, Dubai, UAE',
        ]);

        $wh2 = Warehouse::create([
            'name' => 'Boston Logistics Hub (WH-02)',
            'location' => 'Boston, MA, USA',
        ]);

        $binA1 = WarehouseBin::create(['warehouse_id' => $wh1->id, 'bin_code' => 'Bin A-1']);
        $binA2 = WarehouseBin::create(['warehouse_id' => $wh1->id, 'bin_code' => 'Bin A-2']);
        $binB1 = WarehouseBin::create(['warehouse_id' => $wh1->id, 'bin_code' => 'Bin B-1']);
        $binC1 = WarehouseBin::create(['warehouse_id' => $wh2->id, 'bin_code' => 'Bin C-1']);

        // ──────────────────────────────────────────────────────────────
        // 5. SUPPLIERS (Global Tech Parts LLC)
        // ──────────────────────────────────────────────────────────────
        DB::table('suppliers')->truncate();
        $supplier = Supplier::create([
            'name' => 'Global Tech Parts LLC',
            'contact_person' => 'Alex Mercer',
            'email' => 'alex@globaltechparts.com',
            'phone' => '+1-555-0244',
            'address' => '500 Industrial Parkway, Chicago, IL 60609',
            'tax_id' => 'US-1294820',
            'is_active' => true,
        ]);

        DB::table('contact_persons')->insert([
            'contactable_type' => 'App\\Models\\Supplier',
            'contactable_id' => $supplier->id,
            'name' => 'Alex Mercer',
            'email' => 'alex@globaltechparts.com',
            'phone' => '+1-555-0244',
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'App\\Models\\Supplier',
            'addressable_id' => $supplier->id,
            'type' => 'both',
            'address_line_1' => '500 Industrial Parkway',
            'city' => 'Chicago',
            'state' => 'IL',
            'postal_code' => '60609',
            'country' => 'USA',
            'is_default_billing' => true,
            'is_default_shipping' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 6. CUSTOMERS (TechVenture Solutions Inc. & Mega Builders Inc)
        // ──────────────────────────────────────────────────────────────
        DB::table('customers')->truncate();
        $customer = Customer::create([
            'name' => 'TechVenture Solutions Inc.',
            'contact_person' => 'Sarah Jenkins',
            'email' => 'sarah@techventure.com',
            'phone' => '+1-555-0199',
            'tax_id' => 'US-8839201',
            'billing_address' => '100 Technology Way, Suite 400, Boston, MA 02110',
            'shipping_address' => '100 Technology Way, Suite 400, Boston, MA 02110',
        ]);

        DB::table('contact_persons')->insert([
            'contactable_type' => 'App\\Models\\Customer',
            'contactable_id' => $customer->id,
            'name' => 'Sarah Jenkins',
            'email' => 'sarah@techventure.com',
            'phone' => '+1-555-0199',
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('addresses')->insert([
            [
                'addressable_type' => 'App\\Models\\Customer',
                'addressable_id' => $customer->id,
                'type' => 'billing',
                'address_line_1' => '100 Technology Way, Suite 400',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02110',
                'country' => 'USA',
                'is_default_billing' => true,
                'is_default_shipping' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'addressable_type' => 'App\\Models\\Customer',
                'addressable_id' => $customer->id,
                'type' => 'shipping',
                'address_line_1' => '100 Technology Way, Suite 400',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02110',
                'country' => 'USA',
                'is_default_billing' => false,
                'is_default_shipping' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ]);

        // Second customer for additional data integrity
        $customer2 = Customer::create([
            'name' => 'Mega Builders Inc',
            'contact_person' => 'Alice Brown',
            'email' => 'alice@megabuilders.com',
            'phone' => '+1-555-0322',
            'tax_id' => 'US-9922831',
            'billing_address' => '456 Construction St, Chicago, IL 60601',
            'shipping_address' => '456 Construction St, Chicago, IL 60601',
        ]);

        // ──────────────────────────────────────────────────────────────
        // 7. PRODUCTS (Finished Good & Raw Components)
        // ──────────────────────────────────────────────────────────────
        DB::table('products')->truncate();
        DB::table('product_supplier')->truncate();

        // 7A. Finished Good
        $fgProduct = Product::create([
            'sku' => 'FG-SRV-42U',
            'name' => 'Enterprise Server Rack 42U',
            'category' => 'Server Hardware',
            'barcode' => '8000000000010',
            'unit_of_measure' => 'unit',
            'cost_price' => 1500.00,
            'unit_price' => 2500.00,
            'reorder_level' => 5,
            'product_type' => 'storable',
            'route' => 'manufacture',
            'velocity' => 1.5,
            'lead_time_days' => 10,
            'dynamic_reorder_level' => 20,
        ]);

        // 7B. Raw Components
        $rmSteel = Product::create([
            'sku' => 'RM-STL-42U',
            'name' => 'Steel Frame Heavy Duty',
            'category' => 'Raw Materials',
            'barcode' => '8000000000011',
            'unit_of_measure' => 'unit',
            'cost_price' => 500.00,
            'unit_price' => 750.00,
            'reorder_level' => 10,
            'product_type' => 'storable',
            'route' => 'buy',
            'velocity' => 2.0,
            'lead_time_days' => 5,
            'dynamic_reorder_level' => 15,
        ]);

        $rmPdu = Product::create([
            'sku' => 'RM-PDU-8',
            'name' => 'PDU Power Strip 8-Outlet',
            'category' => 'Electronics',
            'barcode' => '8000000000012',
            'unit_of_measure' => 'unit',
            'cost_price' => 150.00,
            'unit_price' => 225.00,
            'reorder_level' => 8,
            'product_type' => 'storable',
            'route' => 'buy',
            'velocity' => 3.0,
            'lead_time_days' => 7,
            'dynamic_reorder_level' => 29,
        ]);

        $rmFan = Product::create([
            'sku' => 'RM-FAN-120',
            'name' => 'Cooling Fan Unit 120mm',
            'category' => 'Electronics',
            'barcode' => '8000000000013',
            'unit_of_measure' => 'unit',
            'cost_price' => 70.00,
            'unit_price' => 105.00,
            'reorder_level' => 15,
            'product_type' => 'storable',
            'route' => 'buy',
            'velocity' => 5.0,
            'lead_time_days' => 3,
            'dynamic_reorder_level' => 20,
        ]);

        $rmScrew = Product::create([
            'sku' => 'RM-SCR-PK',
            'name' => 'Mounting Screws Pack',
            'category' => 'Raw Materials',
            'barcode' => '8000000000014',
            'unit_of_measure' => 'unit',
            'cost_price' => 10.00,
            'unit_price' => 15.00,
            'reorder_level' => 20,
            'product_type' => 'storable',
            'route' => 'buy',
            'velocity' => 10.0,
            'lead_time_days' => 2,
            'dynamic_reorder_level' => 25,
        ]);

        // Link components to supplier
        foreach ([$rmSteel, $rmPdu, $rmFan, $rmScrew] as $rm) {
            DB::table('product_supplier')->insert([
                'product_id' => $rm->id,
                'supplier_id' => $supplier->id,
                'supplier_sku' => 'SUP-' . $rm->sku,
                'price' => $rm->cost_price,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ──────────────────────────────────────────────────────────────
        // 8. BILL OF MATERIALS (BOM)
        // ──────────────────────────────────────────────────────────────
        DB::table('bills_of_materials')->truncate();
        DB::table('bom_items')->truncate();

        $bom = BillOfMaterial::create([
            'product_id' => $fgProduct->id,
            'bom_code' => 'BOM-SRV-42U',
            'name' => 'Enterprise Server Rack 42U Recipe',
            'output_quantity' => 1.0000,
        ]);

        BomItem::create(['bom_id' => $bom->id, 'component_product_id' => $rmSteel->id, 'quantity_required' => 1.0000]);
        BomItem::create(['bom_id' => $bom->id, 'component_product_id' => $rmPdu->id, 'quantity_required' => 2.0000]);
        BomItem::create(['bom_id' => $bom->id, 'component_product_id' => $rmFan->id, 'quantity_required' => 4.0000]);
        BomItem::create(['bom_id' => $bom->id, 'component_product_id' => $rmScrew->id, 'quantity_required' => 1.0000]);

        // ──────────────────────────────────────────────────────────────
        // 9. CRM LEADS & ACTIVITIES
        // ──────────────────────────────────────────────────────────────
        DB::table('crm_leads')->truncate();
        DB::table('crm_activities')->truncate();

        $adminUser = User::where('email', 'admin@example.com')->first() ?? User::factory()->create();

        $lead = CrmLead::create([
            'customer_id' => $customer->id,
            'title' => 'TechVenture Solutions Inc. - Enterprise Server Racks 10 Units',
            'company_name' => 'TechVenture Solutions Inc.',
            'contact_name' => 'Sarah Jenkins',
            'email' => 'sarah@techventure.com',
            'phone' => '+1-555-0199',
            'deal_value' => 25000.00,
            'pipeline_stage' => 'won',
            'deal_probability' => 100,
            'source' => 'Referral',
            'notes' => 'Inquiry for purchasing 10 high-grade Enterprise Server Racks.',
            'assigned_user_id' => $adminUser->id,
        ]);

        CrmActivity::create([
            'crm_lead_id' => $lead->id,
            'type' => 'meeting',
            'description' => 'Finalized contract terms and rack dimensions with TechVenture Solutions.',
            'activity_date' => $now->toDateString(),
            'user_id' => $adminUser->id,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 10. QUOTATIONS
        // ──────────────────────────────────────────────────────────────
        DB::table('quotations')->truncate();
        DB::table('quotation_items')->truncate();

        $quotation = Quotation::create([
            'reference_no' => 'QT-2026-001',
            'customer_id' => $customer->id,
            'crm_lead_id' => $lead->id,
            'status' => 'accepted',
            'valid_until' => $now->copy()->addDays(30)->toDateString(),
            'total_amount' => 29500.00, // 25000 + 4500 tax
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $fgProduct->id,
            'quantity' => 10,
            'unit_price' => 2500.00,
            'total_price' => 25000.00,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 11. SALES ORDERS
        // ──────────────────────────────────────────────────────────────
        DB::table('sales_orders')->truncate();
        DB::table('sales_order_items')->truncate();

        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'status' => 'processing',
            'tax_amount' => 4500.00,
            'total_amount' => 29500.00,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $fgProduct->id,
            'quantity' => 10,
            'unit_price' => 2500.00,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 12. MANUFACTURING ORDERS (MO)
        // ──────────────────────────────────────────────────────────────
        DB::table('manufacturing_orders')->truncate();

        // MO-2026-001 completed (producing 6 units)
        $mo1 = ManufacturingOrder::create([
            'mo_number' => 'MO-2026-001',
            'product_id' => $fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_to_produce' => 6.0000,
            'status' => 'completed',
            'scheduled_start_date' => $now->copy()->subDays(3)->toDateString(),
            'actual_completed_date' => $now->copy()->subDays(1)->toDateString(),
            'sales_order_id' => $salesOrder->id,
        ]);

        // MO-2026-002 in progress (producing 4 units)
        $mo2 = ManufacturingOrder::create([
            'mo_number' => 'MO-2026-002',
            'product_id' => $fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_to_produce' => 4.0000,
            'status' => 'in_progress',
            'scheduled_start_date' => $now->copy()->subDays(1)->toDateString(),
            'sales_order_id' => $salesOrder->id,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 13. PURCHASE ORDERS (PO)
        // ──────────────────────────────────────────────────────────────
        DB::table('purchase_orders')->truncate();
        DB::table('purchase_order_items')->truncate();

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'subtotal' => 10900.00,
            'gst_type' => 'exclusive',
            'gst_percentage' => 18,
            'gst_amount' => 1962.00,
            'total_amount' => 12862.00,
        ]);

        PurchaseOrderItem::create(['purchase_order_id' => $purchaseOrder->id, 'product_id' => $rmSteel->id, 'quantity' => 10, 'unit_price' => 500.00, 'received_quantity' => 10]);
        PurchaseOrderItem::create(['purchase_order_id' => $purchaseOrder->id, 'product_id' => $rmPdu->id, 'quantity' => 20, 'unit_price' => 150.00, 'received_quantity' => 20]);
        PurchaseOrderItem::create(['purchase_order_id' => $purchaseOrder->id, 'product_id' => $rmFan->id, 'quantity' => 40, 'unit_price' => 70.00, 'received_quantity' => 40]);
        PurchaseOrderItem::create(['purchase_order_id' => $purchaseOrder->id, 'product_id' => $rmScrew->id, 'quantity' => 10, 'unit_price' => 10.00, 'received_quantity' => 10]);

        // ──────────────────────────────────────────────────────────────
        // 14. GOODS RECEIPT NOTES (GRN) & QUALITY CHECKS
        // ──────────────────────────────────────────────────────────────
        DB::table('goods_receipt_notes')->truncate();
        DB::table('quality_checks')->truncate();

        $grn = GoodsReceiptNote::create([
            'purchase_order_id' => $purchaseOrder->id,
            'user_id' => $adminUser->id,
            'status' => 'completed',
            'notes' => 'Received heavy duty steel frames, PDUs, fans, and mounting screws in perfect condition.',
        ]);

        $qc = QualityCheck::create([
            'reference_type' => GoodsReceiptNote::class,
            'reference_id' => $grn->id,
            'product_id' => $rmSteel->id,
            'status' => 'passed',
            'findings_notes' => 'Passed full physical measurement audit and voltage checking.',
            'inspector_user_id' => $adminUser->id,
            'inspected_at' => $now,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 15. SEED STOCK & INVENTORY TRANSACTIONS
        // ──────────────────────────────────────────────────────────────
        DB::table('bin_product_stock')->truncate();
        DB::table('inventory_transactions')->truncate();

        // Seed stock for components in Bin A-1 (Dubai)
        BinProductStock::create(['warehouse_bin_id' => $binA1->id, 'product_id' => $rmSteel->id, 'quantity' => 10.0000, 'unit_cost' => 500.00]);
        BinProductStock::create(['warehouse_bin_id' => $binA1->id, 'product_id' => $rmPdu->id, 'quantity' => 20.0000, 'unit_cost' => 150.00]);
        BinProductStock::create(['warehouse_bin_id' => $binA1->id, 'product_id' => $rmFan->id, 'quantity' => 40.0000, 'unit_cost' => 70.00]);
        BinProductStock::create(['warehouse_bin_id' => $binA1->id, 'product_id' => $rmScrew->id, 'quantity' => 10.0000, 'unit_cost' => 10.00]);

        // Seed finished goods stock in Bin A-2 (Dubai) from completed MO1
        BinProductStock::create(['warehouse_bin_id' => $binA2->id, 'product_id' => $fgProduct->id, 'quantity' => 6.0000, 'unit_cost' => 1500.00]);

        // Seed some stock in WH-02 (Boston) to show global distribution
        BinProductStock::create(['warehouse_bin_id' => $binC1->id, 'product_id' => $fgProduct->id, 'quantity' => 4.0000, 'unit_cost' => 1500.00]);

        // Log incoming transactions
        $incomingQuantities = [
            ['product' => $rmSteel, 'qty' => 10],
            ['product' => $rmPdu, 'qty' => 20],
            ['product' => $rmFan, 'qty' => 40],
            ['product' => $rmScrew, 'qty' => 10],
        ];
        foreach ($incomingQuantities as $item) {
            $prod = $item['product'];
            $qty = $item['qty'];
            InventoryTransaction::create([
                'product_id' => $prod->id,
                'from_bin_id' => null,
                'to_bin_id' => $binA1->id,
                'type' => 'in',
                'quantity' => $qty,
                'reference_type' => GoodsReceiptNote::class,
                'reference_id' => $grn->id,
                'notes' => 'Received from PO PO-2026-001',
                'user_id' => $adminUser->id,
            ]);
        }

        // Log completed manufacturing production transaction
        InventoryTransaction::create([
            'product_id' => $fgProduct->id,
            'from_bin_id' => null,
            'to_bin_id' => $binA2->id,
            'type' => 'in',
            'quantity' => 6.0000,
            'reference_type' => ManufacturingOrder::class,
            'reference_id' => $mo1->id,
            'notes' => 'Produced by Manufacturing Order MO-2026-001',
            'user_id' => $adminUser->id,
        ]);

        // Log manufacturing consumptions
        InventoryTransaction::create(['product_id' => $rmSteel->id, 'from_bin_id' => $binA1->id, 'to_bin_id' => null, 'type' => 'out', 'quantity' => 6.0000, 'reference_type' => ManufacturingOrder::class, 'reference_id' => $mo1->id, 'notes' => 'Consumed for MO MO-2026-001', 'user_id' => $adminUser->id]);
        InventoryTransaction::create(['product_id' => $rmPdu->id, 'from_bin_id' => $binA1->id, 'to_bin_id' => null, 'type' => 'out', 'quantity' => 12.0000, 'reference_type' => ManufacturingOrder::class, 'reference_id' => $mo1->id, 'notes' => 'Consumed for MO MO-2026-001', 'user_id' => $adminUser->id]);
        InventoryTransaction::create(['product_id' => $rmFan->id, 'from_bin_id' => $binA1->id, 'to_bin_id' => null, 'type' => 'out', 'quantity' => 24.0000, 'reference_type' => ManufacturingOrder::class, 'reference_id' => $mo1->id, 'notes' => 'Consumed for MO MO-2026-001', 'user_id' => $adminUser->id]);
        InventoryTransaction::create(['product_id' => $rmScrew->id, 'from_bin_id' => $binA1->id, 'to_bin_id' => null, 'type' => 'out', 'quantity' => 6.0000, 'reference_type' => ManufacturingOrder::class, 'reference_id' => $mo1->id, 'notes' => 'Consumed for MO MO-2026-001', 'user_id' => $adminUser->id]);

        // ──────────────────────────────────────────────────────────────
        // 16. INVOICES, RECEIVABLES, PAYABLES & JOURNAL ENTRIES
        // ──────────────────────────────────────────────────────────────
        DB::table('invoices')->truncate();
        DB::table('invoice_items')->truncate();
        DB::table('account_receivables')->truncate();
        DB::table('account_payables')->truncate();
        DB::table('payment_logs')->truncate();
        DB::table('journal_entries')->truncate();
        DB::table('journal_lines')->truncate();

        // 16A. Sales Invoice (Receivables)
        $invoice = Invoice::create([
            'sales_order_id' => $salesOrder->id,
            'status' => 'unpaid',
            'amount' => 29500.00,
            'currency_code' => 'USD',
            'exchange_rate' => 1.0,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $fgProduct->id,
            'quantity' => 10,
            'unit_price' => 2500.00,
        ]);

        AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => 29500.00,
            'status' => 'unpaid',
        ]);

        // 16B. Purchase Payable (Payables)
        $ap = AccountPayable::create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'amount' => 12862.00,
            'status' => 'unpaid',
        ]);

        // ──────────────────────────────────────────────────────────────
        // 17. FLEET & LOGISTICS
        // ──────────────────────────────────────────────────────────────
        DB::table('vehicles')->truncate();
        DB::table('drivers')->truncate();
        DB::table('shipments')->truncate();
        DB::table('projects')->truncate();
        DB::table('predictive_maintenances')->truncate();
        DB::table('fuel_anomalies')->truncate();
        DB::table('equipment_usage_charges')->truncate();
        DB::table('fleet_expenses')->truncate();
        DB::table('vehicle_damage_audits')->truncate();
        DB::table('fleet_marketplace_transfers')->truncate();

        // 17A. Fleet Project
        $project = Project::create([
            'name' => 'TechVenture Rack Deployment',
            'description' => 'Deploying Enterprise Server Racks to client site.',
            'status' => 'active',
        ]);

        $project2 = Project::create([
            'name' => 'Bechtel Terminal Expansion',
            'description' => 'Chicago site terminal configuration.',
            'status' => 'active',
        ]);

        // 17B. Vehicles
        $vehicle = Vehicle::create([
            'license_plate' => 'DXB-G-55891',
            'brand' => 'Scania',
            'model' => 'P-Series Flatbed',
            'year' => 2023,
            'fuel_type' => 'Diesel',
            'purchase_cost' => 120000.00,
            'purchase_date' => $now->copy()->subYears(1)->toDateString(),
            'lifecycle_stage' => 'active',
            'health_score' => 95,
            'risk_level' => 'low',
            'carbon_emissions' => 220.00,
            'qr_code_token' => 'V-DXB-G-55891-TOK',
            'digital_twin_status' => [
                'engine_rpm' => 1800,
                'speed_kmh' => 65,
                'coolant_temp_c' => 88,
                'tire_pressure_psi' => 110,
            ],
            'type' => 'Flatbed Truck',
            'status' => 'available',
            'capacity' => 15000,
            'latitude' => 25.1972,
            'longitude' => 55.2796,
        ]);

        // Other vehicles for lists
        Vehicle::create([
            'license_plate' => 'DXB-F-12340', 'type' => 'Heavy Truck', 'capacity' => 20000, 'status' => 'available', 'latitude' => 25.2048, 'longitude' => 55.2708,
            'brand' => 'Volvo', 'model' => 'FH16', 'year' => 2022, 'fuel_type' => 'Diesel', 'lifecycle_stage' => 'active', 'health_score' => 98,
        ]);

        // 17C. Drivers
        $driverUser = User::firstOrCreate([
            'email' => 'mohammed.alrashidi@driver.erp',
        ], [
            'name' => 'Mohammed Al-Rashidi',
            'password' => bcrypt('password'),
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'license_number' => 'DL-AE-2024-0091',
            'status' => 'online',
            'latitude' => 25.1972,
            'longitude' => 55.2796,
            'current_vehicle_id' => $vehicle->id,
            'safety_score' => 98,
            'fuel_efficiency_score' => 94,
            'attendance_score' => 100,
            'overall_rating' => 'A',
            'rewards_count' => 3,
            'penalties_count' => 0,
        ]);

        // 17D. Shipments
        $shipment = Shipment::create([
            'sales_order_id' => $salesOrder->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => 'in_transit',
            'tracking_number' => 'TRK-2026-DXB-001',
            'origin_address' => 'WH-01 Jebel Ali warehouse, Dubai, UAE',
            'destination_address' => '100 Technology Way, Boston, MA 02110',
            'origin_lat' => 25.0083,
            'origin_lng' => 55.0694,
            'dest_lat' => 42.3601,
            'dest_lng' => -71.0589,
        ]);

        // 17E. Predictive Maintenance
        PredictiveMaintenance::create([
            'vehicle_id' => $vehicle->id,
            'likely_issue' => 'Hydraulic Pump Wear',
            'prediction_days_range' => 15,
            'confidence_score' => 88,
            'status' => 'active',
        ]);

        // 17F. Fuel Theft Anomaly
        FuelAnomaly::create([
            'vehicle_id' => $vehicle->id,
            'expected_fuel' => 150.00,
            'actual_fuel' => 120.00,
            'variance' => 30.00,
            'anomaly_date' => $now->copy()->subDays(2)->toDateString(),
            'notes' => 'Sudden drop in fuel level during parking.',
        ]);

        // 17G. Equipment Usage Billing & Expense
        $usageCharge = EquipmentUsageCharge::create([
            'vehicle_id' => $vehicle->id,
            'project_id' => $project->id,
            'usage_hours' => 10.00,
            'hourly_rate' => 100.00,
            'total_charge' => 1000.00,
            'billing_date' => $now->toDateString(),
            'status' => 'pending',
        ]);

        FleetExpense::create([
            'vehicle_id' => $vehicle->id,
            'category' => 'repairs',
            'amount' => 1000.00,
            'date_incurred' => $now->toDateString(),
            'project_id' => $project->id,
        ]);

        // 17H. Damage Audit
        VehicleDamageAudit::create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'audit_date' => $now->toDateString(),
            'before_trip_scratches' => 1,
            'after_trip_scratches' => 1,
            'before_trip_dents' => 0,
            'after_trip_dents' => 0,
            'status' => 'logged',
        ]);

        // 17I. Marketplace Transfer Request
        FleetMarketplaceTransfer::create([
            'vehicle_id' => $vehicle->id,
            'from_project_id' => $project->id,
            'to_project_id' => $project2->id,
            'request_date' => $now->toDateString(),
            'status' => 'requested',
        ]);

        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
