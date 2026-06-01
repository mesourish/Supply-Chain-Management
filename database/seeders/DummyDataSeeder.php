<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        $now = Carbon::now();

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
    }
}
