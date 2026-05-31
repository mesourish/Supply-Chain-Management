<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\SystemConstant;

class RealisticProductsSeeder extends Seeder
{
    public function run()
    {
        $categories = [
            'Electronics',
            'Furniture',
            'Networking',
            'Stationery',
            'Enterprise Hardware',
            'Power Tools'
        ];

        // Ensure categories exist in SystemConstant
        foreach ($categories as $cat) {
            SystemConstant::updateOrCreate(
                ['type' => 'product_category', 'name' => $cat],
                ['value' => strtolower(str_replace(' ', '_', $cat)), 'is_active' => true]
            );
        }

        $units = ['PCs', 'Rolls', 'Cartons', 'Packs', 'Kits'];
        
        foreach ($units as $unit) {
            SystemConstant::updateOrCreate(
                ['type' => 'unit_of_measure', 'name' => $unit],
                ['value' => strtolower($unit), 'is_active' => true]
            );
        }

        $products = [
            [
                'sku' => 'ELEC-LAP-001',
                'barcode' => '889894452134',
                'name' => 'ThinkPad T14 Gen 3',
                'category' => 'Electronics',
                'brand' => 'Lenovo',
                'description' => '14" Business Laptop with Intel Core i7, 16GB RAM, 512GB SSD.',
                'unit_of_measure' => 'PCs',
                'weight' => 1.5,
                'cost_price' => 1100.00,
                'unit_price' => 1350.00,
                'reorder_level' => 10,
            ],
            [
                'sku' => 'ELEC-MON-002',
                'barcode' => '884116375089',
                'name' => 'Dell UltraSharp 27" 4K Monitor',
                'category' => 'Electronics',
                'brand' => 'Dell',
                'description' => 'U2723QE 4K USB-C Hub Monitor.',
                'unit_of_measure' => 'PCs',
                'weight' => 6.6,
                'cost_price' => 450.00,
                'unit_price' => 600.00,
                'reorder_level' => 15,
            ],
            [
                'sku' => 'OFF-CHR-003',
                'barcode' => '711314592031',
                'name' => 'Ergonomic Mesh Office Chair',
                'category' => 'Furniture',
                'brand' => 'Herman Miller',
                'description' => 'Aeron Chair with adjustable lumbar support and tilt.',
                'unit_of_measure' => 'PCs',
                'weight' => 18.0,
                'cost_price' => 850.00,
                'unit_price' => 1200.00,
                'reorder_level' => 5,
            ],
            [
                'sku' => 'OFF-DSK-004',
                'barcode' => '819385023912',
                'name' => 'Standing Desk Dual Motor',
                'category' => 'Furniture',
                'brand' => 'FlexiSpot',
                'description' => 'Electric height adjustable standing desk (60x30 inches).',
                'unit_of_measure' => 'PCs',
                'weight' => 30.5,
                'cost_price' => 250.00,
                'unit_price' => 380.00,
                'reorder_level' => 10,
            ],
            [
                'sku' => 'NET-RTR-005',
                'barcode' => '882658829555',
                'name' => 'Cisco Meraki MX68',
                'category' => 'Networking',
                'brand' => 'Cisco',
                'description' => 'Cloud Managed Security and SD-WAN Appliance.',
                'unit_of_measure' => 'PCs',
                'weight' => 1.2,
                'cost_price' => 600.00,
                'unit_price' => 750.00,
                'reorder_level' => 8,
            ],
            [
                'sku' => 'NET-CBL-006',
                'barcode' => '032886029311',
                'name' => 'Cat6 Ethernet Cable (1000ft)',
                'category' => 'Networking',
                'brand' => 'Southwire',
                'description' => 'Plenum (CMP) Rated, 23AWG Solid Bare Copper.',
                'unit_of_measure' => 'Rolls',
                'weight' => 12.0,
                'cost_price' => 180.00,
                'unit_price' => 240.00,
                'reorder_level' => 4,
            ],
            [
                'sku' => 'STAT-PAP-007',
                'barcode' => '014725836952',
                'name' => 'A4 Multipurpose Copy Paper',
                'category' => 'Stationery',
                'brand' => 'HP',
                'description' => '20lb, 92 Bright, 500 Sheets/Ream, 10 Reams/Carton.',
                'unit_of_measure' => 'Cartons',
                'weight' => 22.0,
                'cost_price' => 35.00,
                'unit_price' => 55.00,
                'reorder_level' => 50,
            ],
            [
                'sku' => 'STAT-PEN-008',
                'barcode' => '072838310200',
                'name' => 'Pilot G2 Retractable Gel Pens',
                'category' => 'Stationery',
                'brand' => 'Pilot',
                'description' => 'Fine Point (0.7mm), Black Ink, 12-Pack.',
                'unit_of_measure' => 'Packs',
                'weight' => 0.3,
                'cost_price' => 8.50,
                'unit_price' => 14.00,
                'reorder_level' => 100,
            ],
            [
                'sku' => 'ELEC-SRV-009',
                'barcode' => '884116347895',
                'name' => 'PowerEdge R750 Rack Server',
                'category' => 'Enterprise Hardware',
                'brand' => 'Dell',
                'description' => '2U Rack Server, Dual Xeon Silver 4310, 64GB RAM.',
                'unit_of_measure' => 'PCs',
                'weight' => 28.6,
                'cost_price' => 3200.00,
                'unit_price' => 4100.00,
                'reorder_level' => 2,
            ],
            [
                'sku' => 'TOOL-DRL-010',
                'barcode' => '885911496032',
                'name' => '20V Max Cordless Drill Combo Kit',
                'category' => 'Power Tools',
                'brand' => 'DeWalt',
                'description' => 'Compact Drill/Driver with 2 Batteries and Charger.',
                'unit_of_measure' => 'Kits',
                'weight' => 5.2,
                'cost_price' => 85.00,
                'unit_price' => 129.00,
                'reorder_level' => 20,
            ]
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(['sku' => $p['sku']], $p);
        }
    }
}
