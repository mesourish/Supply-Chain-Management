<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Product;

class RealisticSuppliersSeeder extends Seeder
{
    public function run()
    {
        $suppliers = [
            [
                'name' => 'TechSource Distributors',
                'contact_person' => 'Michael Chang',
                'email' => 'sales@techsource-dist.com',
                'phone' => '+1-555-019-8822',
                'tax_id' => 'TX-99882211',
                'address' => '1200 Innovation Drive, Silicon Valley, CA 94025',
                'is_active' => true,
                'categories' => ['Electronics', 'Networking', 'Enterprise Hardware']
            ],
            [
                'name' => 'OfficePro Supplies Inc.',
                'contact_person' => 'Sarah Jenkins',
                'email' => 'orders@officepro.net',
                'phone' => '+1-555-014-3344',
                'tax_id' => 'TX-44335566',
                'address' => '450 Corporate Blvd, Suite 100, Chicago, IL 60601',
                'is_active' => true,
                'categories' => ['Furniture', 'Stationery']
            ],
            [
                'name' => 'Industrial Hardware Co.',
                'contact_person' => 'David Miller',
                'email' => 'b2b@industrialhardware.com',
                'phone' => '+1-555-017-9911',
                'tax_id' => 'TX-77889900',
                'address' => '7800 Manufacturing Way, Detroit, MI 48211',
                'is_active' => true,
                'categories' => ['Power Tools', 'Enterprise Hardware']
            ]
        ];

        foreach ($suppliers as $sData) {
            $categories = $sData['categories'];
            unset($sData['categories']);
            
            $supplier = Supplier::updateOrCreate(
                ['email' => $sData['email']], 
                $sData
            );

            // Fetch products that match the supplier's categories
            $products = Product::whereIn('category', $categories)->get();

            $syncData = [];
            foreach ($products as $product) {
                // Generate a realistic supplier SKU
                $supplierSku = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $supplier->name), 0, 3)) . '-' . $product->sku;
                
                // Set the supplier price slightly varied around the standard cost price
                $price = $product->cost_price * rand(95, 105) / 100;
                
                $syncData[$product->id] = [
                    'price' => round($price, 2),
                    'supplier_sku' => $supplierSku
                ];
            }
            
            $supplier->products()->syncWithoutDetaching($syncData);
        }
    }
}
