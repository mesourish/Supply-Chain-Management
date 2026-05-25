<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;
use App\Models\User;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        $now = Carbon::now();
        $user = User::first() ?? User::factory()->create();

        // Suppliers
        $suppliers = [];
        for ($i = 0; $i < 10; $i++) {
            $suppliers[] = [
                'name' => $faker->company,
                'contact_person' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'tax_id' => $faker->numerify('TAX-####-####'),
                'address' => $faker->address,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('suppliers')->insert($suppliers);
        $supplierIds = DB::table('suppliers')->pluck('id')->toArray();

        // Customers
        $customers = [];
        for ($i = 0; $i < 10; $i++) {
            $customers[] = [
                'name' => $faker->company,
                'contact_person' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'tax_id' => $faker->numerify('TAX-####-####'),
                'billing_address' => $faker->address,
                'shipping_address' => $faker->address,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('customers')->insert($customers);
        $customerIds = DB::table('customers')->pluck('id')->toArray();

        // Warehouses & Bins
        $warehouses = [];
        for ($i = 0; $i < 5; $i++) {
            $warehouses[] = [
                'name' => 'Warehouse ' . $faker->city,
                'location' => $faker->address,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('warehouses')->insert($warehouses);
        $warehouseIds = DB::table('warehouses')->pluck('id')->toArray();

        $bins = [];
        $seq = 1;
        foreach ($warehouseIds as $wId) {
            // Seed 2 zones, 2 racks per zone, 3 shelves, 2 aisles (24 bins total per warehouse)
            foreach (['A', 'B'] as $zone) {
                foreach (['R1', 'R2'] as $rack) {
                    for ($row = 1; $row <= 3; $row++) { // Shelf
                        for ($col = 1; $col <= 2; $col++) { // Aisle
                            $shelf = 'S' . $row;
                            $aisle = str_pad($col, 2, '0', STR_PAD_LEFT);
                            $binCode = $zone . '-' . $rack . '-' . $shelf . '-' . $aisle . '-' . $wId;
                            
                            $bins[] = [
                                'warehouse_id' => $wId,
                                'bin_code' => $binCode,
                                'zone' => $zone,
                                'aisle' => $aisle,
                                'rack' => $rack,
                                'shelf' => $shelf,
                                'bin_type' => 'standard',
                                'max_weight_kg' => 500,
                                'bin_sequence_no' => $seq++,
                                'is_active' => true,
                                'bin_status' => 'ACTIVE',
                                'pick_face_flag' => false,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            }
        }
        DB::table('warehouse_bins')->insert($bins);
        $binIds = DB::table('warehouse_bins')->pluck('id')->toArray();

        // Products
        $products = [];
        $realProducts = [
            'Apple iPhone 15 Pro', 'Samsung Galaxy S24', 'Dell XPS 15', 'MacBook Air M3', 'Sony WH-1000XM5',
            'Herman Miller Aeron', 'IKEA Bekant Desk', 'Logitech MX Master 3S', 'Keychron K2 Keyboard', 'LG C3 65" OLED TV',
            'Dyson V15 Vacuum', 'Breville Barista Express', 'Ninja Air Fryer', 'Sony PlayStation 5', 'Xbox Series X',
            'Nintendo Switch OLED', 'Bose QuietComfort 45', 'GoPro HERO12 Black', 'DJI Mini 4 Pro', 'Kindle Paperwhite',
            'Google Pixel 8 Pro', 'Apple iPad Air', 'Samsung Odyssey G9', 'Secretlab Titan Evo', 'Corsair 32GB RAM',
            'NVIDIA RTX 4080', 'AMD Ryzen 9', 'Samsung 2TB SSD', 'Anker Power Bank', 'Yeti Rambler'
        ];
        $categories = ['Electronics', 'Furniture', 'Stationery', 'Hardware'];
        foreach ($realProducts as $index => $prodName) {
            $cost = $faker->randomFloat(2, 5, 500);
            $products[] = [
                'sku' => $faker->unique()->numerify('SKU-####'),
                'barcode' => $faker->unique()->ean13,
                'name' => $prodName,
                'category' => $faker->randomElement($categories),
                'brand' => $faker->company,
                'description' => $faker->sentence,
                'unit_of_measure' => 'pcs',
                'weight' => $faker->randomFloat(2, 0.5, 50),
                'cost_price' => $cost,
                'unit_price' => $cost * 1.5,
                'reorder_level' => $faker->numberBetween(10, 50),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('products')->insert($products);
        $productIds = DB::table('products')->pluck('id')->toArray();

        // Purchase Orders
        $purchaseOrders = [];
        for ($i = 0; $i < 20; $i++) {
            $purchaseOrders[] = [
                'supplier_id' => $faker->randomElement($supplierIds),
                'status' => $faker->randomElement(['draft', 'approved', 'received']),
                'total_amount' => 0, // Will update later
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('purchase_orders')->insert($purchaseOrders);
        $poIds = DB::table('purchase_orders')->pluck('id')->toArray();

        // PO Items
        $poItems = [];
        foreach ($poIds as $poId) {
            $numItems = $faker->numberBetween(1, 5);
            $total = 0;
            for ($j = 0; $j < $numItems; $j++) {
                $qty = $faker->numberBetween(10, 100);
                $price = $faker->randomFloat(2, 5, 100);
                $poItems[] = [
                    'purchase_order_id' => $poId,
                    'product_id' => $faker->randomElement($productIds),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total += ($qty * $price);
            }
            DB::table('purchase_orders')->where('id', $poId)->update(['total_amount' => $total]);
        }
        DB::table('purchase_order_items')->insert($poItems);

        // Sales Orders
        $salesOrders = [];
        for ($i = 0; $i < 20; $i++) {
            $salesOrders[] = [
                'customer_id' => $faker->randomElement($customerIds),
                'status' => $faker->randomElement(['draft', 'confirmed', 'shipped']),
                'total_amount' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('sales_orders')->insert($salesOrders);
        $soIds = DB::table('sales_orders')->pluck('id')->toArray();

        // SO Items
        $soItems = [];
        foreach ($soIds as $soId) {
            $numItems = $faker->numberBetween(1, 5);
            $total = 0;
            for ($j = 0; $j < $numItems; $j++) {
                $qty = $faker->numberBetween(1, 20);
                $price = $faker->randomFloat(2, 20, 200);
                $soItems[] = [
                    'sales_order_id' => $soId,
                    'product_id' => $faker->randomElement($productIds),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total += ($qty * $price);
            }
            DB::table('sales_orders')->where('id', $soId)->update(['total_amount' => $total]);
        }
        DB::table('sales_order_items')->insert($soItems);

        // Inventory Transactions (Initial Stock)
        $transactions = [];
        foreach ($productIds as $pId) {
            $transactions[] = [
                'product_id' => $pId,
                'from_bin_id' => null,
                'to_bin_id' => $faker->randomElement($binIds),
                'type' => 'IN',
                'quantity' => $faker->numberBetween(50, 500),
                'reference_type' => 'App\Models\PurchaseOrder',
                'reference_id' => $faker->randomElement($poIds),
                'notes' => 'Initial Stock',
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('inventory_transactions')->insert($transactions);

        // Vehicles
        $vehicles = [];
        for ($i = 0; $i < 5; $i++) {
            $vehicles[] = [
                'license_plate' => $faker->unique()->regexify('[A-Z]{3}-[0-9]{4}'),
                'type' => $faker->randomElement(['Van', 'Box Truck', 'Semi-Trailer']),
                'status' => 'available',
                'capacity' => $faker->numberBetween(1000, 5000),
                'latitude' => 40.7128 + ($faker->randomFloat(4, -0.1, 0.1)),
                'longitude' => -74.0060 + ($faker->randomFloat(4, -0.1, 0.1)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('vehicles')->insert($vehicles);
        $vehicleIds = DB::table('vehicles')->pluck('id')->toArray();

        // Drivers
        $drivers = [];
        for ($i = 0; $i < 3; $i++) {
            $driverUser = User::factory()->create();
            $drivers[] = [
                'user_id' => $driverUser->id,
                'license_number' => $faker->regexify('[A-Z0-9]{8}'),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('drivers')->insert($drivers);
        $driverIds = DB::table('drivers')->pluck('id')->toArray();

        // Shipments
        $shipments = [];
        foreach ($soIds as $soId) {
            if ($faker->boolean(50)) {
                $status = $faker->randomElement(['pending', 'in_transit', 'delivered']);
                
                $origLat = 40.7128 + ($faker->randomFloat(4, -0.1, 0.1));
                $origLng = -74.0060 + ($faker->randomFloat(4, -0.1, 0.1));
                $destLat = 40.7128 + ($faker->randomFloat(4, -0.3, 0.3));
                $destLng = -74.0060 + ($faker->randomFloat(4, -0.3, 0.3));

                $shipments[] = [
                    'sales_order_id' => $soId,
                    'vehicle_id' => $faker->randomElement($vehicleIds),
                    'driver_id' => $faker->randomElement($driverIds),
                    'status' => $status,
                    'tracking_number' => 'TRK-' . strtoupper($faker->bothify('??####??')),
                    'origin_address' => $faker->address,
                    'destination_address' => $faker->address,
                    'origin_lat' => $origLat,
                    'origin_lng' => $origLng,
                    'dest_lat' => $destLat,
                    'dest_lng' => $destLng,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('shipments')->insert($shipments);

        // Purchase Expenses
        $expenses = [];
        $expenseCategories = ['Shipping & Logistics', 'Customs & Duty', 'Office Supplies', 'Travel', 'Software/IT'];
        for ($i = 0; $i < 15; $i++) {
            $linkType = $faker->randomElement(['po', 'supplier', 'none']);
            $expenses[] = [
                'purchase_order_id' => $linkType === 'po' && !empty($poIds) ? $faker->randomElement($poIds) : null,
                'supplier_id' => $linkType === 'supplier' && !empty($supplierIds) ? $faker->randomElement($supplierIds) : null,
                'category' => $faker->randomElement($expenseCategories),
                'amount' => $faker->randomFloat(2, 50, 2000),
                'expense_date' => clone $now->subDays(rand(1, 60)),
                'reference_number' => $faker->bothify('EXP-####-???'),
                'notes' => $faker->sentence,
                'created_at' => clone $now,
                'updated_at' => clone $now,
            ];
        }
        DB::table('expenses')->insert($expenses);
    }
}
