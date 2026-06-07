<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClearErpDataSelectiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed initial objects, roles, and permissions
        $this->artisan('db:seed');

        // Turn off SQLite foreign key checks directly on the connection
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA foreign_keys = OFF');
        }
    }

    /**
     * Test clearing only the Sales module.
     */
    public function test_can_wipe_only_sales_module_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Ensure database has some product and sales data
        $product = Product::first();
        $customer = Customer::first();
        $supplier = Supplier::first();

        // Count initial records
        $this->assertGreaterThan(0, Product::count());
        $this->assertGreaterThan(0, Supplier::count());
        
        // Assert Sales Orders exist or create one
        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
            'subtotal' => 150.00,
            'total_amount' => 150.00,
        ]);
        $this->assertDatabaseHas('sales_orders', ['id' => $so->id]);

        // Wipe only sales module via Artisan command
        $this->artisan('erp:clear-data', [
            '--modules' => 'sales',
            '--force' => true,
        ])->assertExitCode(0);

        // Verify sales orders and customers are wiped
        $this->assertDatabaseMissing('sales_orders', ['id' => $so->id]);
        $this->assertEquals(0, Customer::count());

        // Verify products, suppliers, and users are NOT wiped
        $this->assertGreaterThan(0, Product::count());
        $this->assertGreaterThan(0, Supplier::count());
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
    }

    /**
     * Test clearing only the Procurement module.
     */
    public function test_can_wipe_only_procurement_module_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $product = Product::first();
        $supplier = Supplier::first();

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'subtotal' => 300.00,
            'total_amount' => 300.00,
        ]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);

        // Wipe only procurement module via Artisan command
        $this->artisan('erp:clear-data', [
            '--modules' => 'procurement',
            '--force' => true,
        ])->assertExitCode(0);

        // Verify POs and suppliers are wiped
        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
        $this->assertEquals(0, Supplier::count());

        // Verify products and users are NOT wiped
        $this->assertGreaterThan(0, Product::count());
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
    }

    /**
     * Test Livewire component settings index integrates with selective wipe.
     */
    public function test_livewire_settings_component_wipes_selected_modules()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $productCount = Product::count();
        $this->assertGreaterThan(0, $productCount);

        // Run Livewire settings index component to wipe sales and finance modules only
        Volt::test('admin.settings.index')
            ->set('selectedWipeModules', ['sales', 'finance'])
            ->call('clearData')
            ->assertHasNoErrors()
            ->assertSet('selectedWipeModules', []);

        // Verify products (Inventory) are still intact
        $this->assertEquals($productCount, Product::count());

        // Verify customers (Sales) are wiped
        $this->assertEquals(0, Customer::count());
    }
}
