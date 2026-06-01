<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class PlatformDataSeeder extends Seeder
{
    public function run(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        $now  = Carbon::now();
        $user = User::first() ?? User::factory()->create();

        // 1. SYSTEM CONSTANTS
        DB::table('system_constants')->insert([
            ['type' => 'product_category', 'name' => 'Raw Materials', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Electronics', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Machinery', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '0% (Exempt)', 'value' => '0', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '5%', 'value' => '5', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '18%', 'value' => '18', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 2. SUPPLIERS
        $suppliers = [
            ['name' => 'Global Electronics Ltd', 'tax_id' => 'TAX-GEL-123', 'contact' => 'John Smith', 'email' => 'john@globalelec.com', 'phone' => '+123456789', 'address' => '123 Tech Park, NY'],
            ['name' => 'Steel Works Corp', 'tax_id' => 'TAX-SWC-456', 'contact' => 'Sarah Jones', 'email' => 'sarah@steelworks.com', 'phone' => '+987654321', 'address' => '456 Metal Ave, TX'],
        ];

        $supplierIds = [];
        foreach ($suppliers as $s) {
            $id = DB::table('suppliers')->insertGetId([
                'name' => $s['name'],
                'contact_person' => $s['contact'],
                'email' => $s['email'],
                'phone' => $s['phone'],
                'address' => $s['address'],
                'tax_id' => $s['tax_id'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $supplierIds[] = $id;

            // Polymorphic contact person
            DB::table('contact_persons')->insert([
                'contactable_type' => 'App\\Models\\Supplier',
                'contactable_id' => $id,
                'name' => $s['contact'],
                'email' => $s['email'],
                'phone' => $s['phone'],
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Polymorphic address
            DB::table('addresses')->insert([
                'addressable_type' => 'App\\Models\\Supplier',
                'addressable_id' => $id,
                'type' => 'both',
                'address_line_1' => $s['address'],
                'is_default_billing' => true,
                'is_default_shipping' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. CUSTOMERS
        $customers = [
            ['name' => 'Mega Builders Inc', 'tax_id' => 'CUS-111', 'contact' => 'Alice Brown', 'email' => 'alice@megabuilders.com', 'phone' => '111222333', 'billing' => '100 Build St', 'shipping' => '100 Build St Site B'],
            ['name' => 'Tech Solutions LLC', 'tax_id' => 'CUS-222', 'contact' => 'Bob Martin', 'email' => 'bob@techsol.com', 'phone' => '444555666', 'billing' => '200 Innovate Way', 'shipping' => '200 Innovate Way'],
        ];

        $customerIds = [];
        foreach ($customers as $c) {
            $id = DB::table('customers')->insertGetId([
                'name' => $c['name'],
                'contact_person' => $c['contact'],
                'email' => $c['email'],
                'phone' => $c['phone'],
                'tax_id' => $c['tax_id'],
                'billing_address' => $c['billing'],
                'shipping_address' => $c['shipping'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $customerIds[] = $id;

            DB::table('contact_persons')->insert([
                'contactable_type' => 'App\\Models\\Customer',
                'contactable_id' => $id,
                'name' => $c['contact'],
                'email' => $c['email'],
                'phone' => $c['phone'],
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('addresses')->insert([
                ['addressable_type' => 'App\\Models\\Customer', 'addressable_id' => $id, 'type' => 'billing', 'address_line_1' => $c['billing'], 'is_default_billing' => true, 'is_default_shipping' => false, 'created_at' => $now, 'updated_at' => $now],
                ['addressable_type' => 'App\\Models\\Customer', 'addressable_id' => $id, 'type' => 'shipping', 'address_line_1' => $c['shipping'], 'is_default_billing' => false, 'is_default_shipping' => true, 'created_at' => $now, 'updated_at' => $now]
            ]);
        }

        // 4. WAREHOUSES AND BINS
        $w1 = DB::table('warehouses')->insertGetId(['name' => 'Main Distribution Center', 'location' => 'Los Angeles, CA', 'created_at' => $now, 'updated_at' => $now]);
        
        $binIds = [];
        for ($i=1; $i<=5; $i++) {
            $binIds[] = DB::table('warehouse_bins')->insertGetId([
                'warehouse_id' => $w1,
                'bin_code' => "A1-S$i",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 5. PRODUCTS WITH FORECASTING
        $products = [
            ['sku' => 'PROD-001', 'name' => 'Industrial PLC Controller', 'cat' => 'Electronics', 'cost' => 1500, 'price' => 2000, 'vel' => 2.5, 'lt' => 14, 'rl' => 35],
            ['sku' => 'PROD-002', 'name' => 'Steel Beam 10ft', 'cat' => 'Raw Materials', 'cost' => 300, 'price' => 450, 'vel' => 10.0, 'lt' => 7, 'rl' => 70],
            ['sku' => 'PROD-003', 'name' => 'Conveyor Motor 5HP', 'cat' => 'Machinery', 'cost' => 800, 'price' => 1200, 'vel' => 1.2, 'lt' => 30, 'rl' => 36],
        ];

        $productIds = [];
        foreach ($products as $idx => $p) {
            $id = DB::table('products')->insertGetId([
                'sku' => $p['sku'],
                'barcode' => '8000000000' . ($idx + 1),
                'name' => $p['name'],
                'category' => $p['cat'],
                'unit_of_measure' => 'unit',
                'cost_price' => $p['cost'],
                'unit_price' => $p['price'],
                'reorder_level' => $p['rl'],
                'velocity' => $p['vel'],
                'lead_time_days' => $p['lt'],
                'dynamic_reorder_level' => ceil($p['vel'] * $p['lt']) + 5, // Safety stock
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $productIds[] = $id;

            // Link to supplier via product_supplier
            DB::table('product_supplier')->insert([
                'product_id' => $id,
                'supplier_id' => $supplierIds[$idx % 2],
                'supplier_sku' => 'SUP-' . $p['sku'],
                'price' => $p['cost'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 6. CRM LEADS
        $lead1 = DB::table('crm_leads')->insertGetId([
            'customer_id' => $customerIds[0],
            'title' => 'Mega Builders Phase 2 Equipment',
            'company_name' => $customers[0]['name'],
            'contact_name' => $customers[0]['contact'],
            'email' => $customers[0]['email'],
            'phone' => $customers[0]['phone'],
            'deal_value' => 50000,
            'pipeline_stage' => 'won',
            'deal_probability' => 100,
            'assigned_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('crm_activities')->insert([
            'crm_lead_id' => $lead1,
            'type' => 'meeting',
            'description' => 'Finalized contract for Phase 2.',
            'activity_date' => $now->toDateString(),
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 7. QUOTATIONS
        $quot1 = DB::table('quotations')->insertGetId([
            'reference_no' => 'QT-2026-0001',
            'customer_id' => $customerIds[0],
            'crm_lead_id' => $lead1,
            'status' => 'accepted',
            'valid_until' => $now->copy()->addDays(30)->toDateString(),
            'total_amount' => 5400, // 12 units of PROD-002
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('quotation_items')->insert([
            'quotation_id' => $quot1,
            'product_id' => $productIds[1],
            'quantity' => 12,
            'unit_price' => 450,
            'total_price' => 5400,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 8. PURCHASE ORDERS & GRN (NOT RECEIVED INTO WAREHOUSE)
        $po1 = DB::table('purchase_orders')->insertGetId([
            'supplier_id' => $supplierIds[1],
            'status' => 'approved', // PO is approved
            'subtotal' => 6000, // 20 units of PROD-002 (cost 300)
            'total_amount' => 6000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('purchase_order_items')->insert([
            'purchase_order_id' => $po1,
            'product_id' => $productIds[1],
            'quantity' => 20,
            'unit_price' => 300,
            'received_quantity' => 0, // NOT received yet
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create GRN but keep it pending/draft so it doesn't affect inventory
        DB::table('goods_receipt_notes')->insertGetId([
            'purchase_order_id' => $po1,
            'user_id' => $user->id,
            'status' => 'pending', // Do NOT set to received
            'notes' => 'Awaiting shipment from supplier. Do not receive into warehouse yet.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Do not insert into `inventory_transactions` or `bin_product_stock`!

        // 9. SALES ORDERS & INVOICES (To satisfy test assertions)
        $so1 = DB::table('sales_orders')->insertGetId([
            'customer_id' => $customerIds[0],
            'status' => 'completed',
            'total_amount' => 5400,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sales_order_items')->insert([
            'sales_order_id' => $so1,
            'product_id' => $productIds[1],
            'quantity' => 12,
            'unit_price' => 450,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('invoices')->insert([
            'sales_order_id' => $so1,
            'status' => 'paid',
            'amount' => 5400,
            'currency_code' => 'USD',
            'exchange_rate' => 1.0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
