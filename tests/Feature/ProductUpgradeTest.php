<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProductUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard permissions, roles, and realistic objects
        $this->artisan('db:seed');
    }

    public function test_can_create_product_with_type_and_route()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $component = Volt::test('products.index')
            ->set('sku', 'PROD-AUTO-TEST-1')
            ->set('name', 'Automated Test Product')
            ->set('cost_price', 10.50)
            ->set('unit_price', 20.00)
            ->set('reorder_level', 15)
            ->set('product_type', 'storable')
            ->set('route', 'make_to_order')
            ->set('min_stock', 5.00)
            ->set('max_stock', 100.00)
            ->set('lead_time_days', 5)
            ->call('save');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-AUTO-TEST-1',
            'product_type' => 'storable',
            'route' => 'make_to_order',
            'min_stock' => 5.0000,
            'max_stock' => 100.0000,
        ]);
    }

    public function test_can_search_products_by_sku_and_name()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Create test products using Product::create
        Product::create([
            'sku' => 'SKU-SEARCH-MATCH',
            'name' => 'Searchable Banana Juice',
            'cost_price' => 1.00,
            'unit_price' => 2.00,
            'reorder_level' => 10,
            'min_stock' => 0.00,
            'max_stock' => 0.00,
            'product_type' => 'storable',
            'route' => 'buy',
        ]);

        Product::create([
            'sku' => 'SKU-OTHER-ITEM',
            'name' => 'Unrelated Apple Juice',
            'cost_price' => 1.00,
            'unit_price' => 2.00,
            'reorder_level' => 10,
            'min_stock' => 0.00,
            'max_stock' => 0.00,
            'product_type' => 'storable',
            'route' => 'buy',
        ]);

        // Test searching by SKU
        Volt::test('products.index')
            ->set('search', 'MATCH')
            ->assertSee('Searchable Banana Juice')
            ->assertDontSee('Unrelated Apple Juice');

        // Test searching by Name
        Volt::test('products.index')
            ->set('search', 'Banana')
            ->assertSee('Searchable Banana Juice')
            ->assertDontSee('Unrelated Apple Juice');
    }
}
