<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\BinProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExtensiveSecurityAndFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed Spatie roles, permissions, and initial objects
        $this->artisan('db:seed');
    }

    /**
     * 1. Guest Authentication protection checks (Black-box)
     */
    public function test_guest_redirects_to_login()
    {
        $protectedUrls = [
            '/dashboard',
            '/products',
            '/warehouses',
            '/crm/leads',
            '/suppliers',
            '/procurement/purchase-orders',
            '/sales/orders',
            '/sales/quotations',
            '/sales/fulfillment',
            '/finance/payables',
            '/finance/receivables',
            '/finance/invoices',
            '/admin/users',
            '/admin/roles',
            '/admin/system-logs',
        ];

        foreach ($protectedUrls as $url) {
            $response = $this->get($url);
            $response->assertStatus(302);
            $response->assertRedirect('/login');
        }
    }

    /**
     * 2. Role-Based Access Control (RBAC) permission restrictions checks (Black-box)
     */
    public function test_non_admin_restricted_from_admin_and_finance_routes()
    {
        // Create a non-admin user (Sales Representative)
        $salesRep = User::factory()->create();
        $salesRep->assignRole('Sales Representative');

        $this->actingAs($salesRep);

        // Assert Sales Rep cannot access Admin screens
        $adminUrls = [
            '/admin/users',
            '/admin/roles',
            '/admin/system-logs',
            '/admin/constants',
        ];

        foreach ($adminUrls as $url) {
            $response = $this->get($url);
            $response->assertStatus(403);
        }

        // Assert Sales Rep cannot access Finance screens
        $financeUrls = [
            '/finance/payables',
            '/finance/receivables',
            '/finance/invoices',
            '/finance/expenses',
            '/finance/payment-certificates',
        ];

        foreach ($financeUrls as $url) {
            $response = $this->get($url);
            $response->assertStatus(403);
        }
    }

    public function test_super_admin_can_access_all_routes()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $urls = [
            '/dashboard',
            '/admin/users',
            '/admin/roles',
            '/admin/system-logs',
            '/admin/constants',
            '/finance/payables',
            '/finance/receivables',
            '/finance/invoices',
            '/finance/expenses',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
        }
    }

    /**
     * 3. Data validation edge cases - Purchase Orders (White-box)
     */
    public function test_purchase_order_validation_fails_for_empty_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        \Livewire\Volt\Volt::test('procurement.purchase-orders.index')
            ->set('supplier_id', '')
            ->set('items', [])
            ->call('save')
            ->assertHasErrors(['supplier_id', 'items']);
    }

    public function test_purchase_order_validation_fails_for_negative_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        \Livewire\Volt\Volt::test('procurement.purchase-orders.index')
            ->set('supplier_id', Supplier::first()->id)
            ->set('items.0.quantity', -5)
            ->call('save')
            ->assertHasErrors([
                'items.0.quantity'
            ]);
    }

    /**
     * 4. Data validation edge cases - Sales Orders (White-box)
     */
    public function test_sales_order_validation_fails_for_empty_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        \Livewire\Volt\Volt::test('sales.orders.index')
            ->set('customer_id', '')
            ->set('items', [])
            ->call('save')
            ->assertHasErrors(['customer_id', 'items']);
    }

    public function test_sales_order_validation_fails_for_negative_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        \Livewire\Volt\Volt::test('sales.orders.index')
            ->set('customer_id', Customer::first()->id)
            ->set('items.0.quantity', -10)
            ->call('save')
            ->assertHasErrors([
                'items.0.quantity'
            ]);
    }

    /**
     * 5. Data validation edge cases - Quotations (White-box)
     */
    public function test_quotation_validation_fails_for_empty_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        \Livewire\Volt\Volt::test('sales.quotations')
            ->set('customer_id', '')
            ->call('saveQuote')
            ->assertHasErrors(['customer_id']);
    }

    public function test_quotation_validation_fails_for_negative_data()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        \Livewire\Volt\Volt::test('sales.quotations')
            ->set('customer_id', Customer::first()->id)
            ->set('items.0.quantity', -2)
            ->call('saveQuote')
            ->assertHasErrors([
                'items.0.quantity'
            ]);
    }

    /**
     * 6. Warehouse Stock Fulfillment constraints (White-box)
     */
    public function test_fulfillment_insufficient_stock_constraint()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $customer = Customer::first();
        $product = Product::first();
        $warehouse = Warehouse::first();
        $bin = WarehouseBin::where('warehouse_id', $warehouse->id)->first();

        // 1. Set bin stock to low quantity (e.g. 5)
        BinProductStock::updateOrCreate(
            ['warehouse_bin_id' => $bin->id, 'product_id' => $product->id],
            ['quantity' => 5]
        );

        // 2. Create a Sales Order requesting 10 items (more than available stock)
        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
        ]);
        $so->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10.00,
        ]);

        // 3. Test fulfillment component - the bin should NOT have enough stock
        \Livewire\Volt\Volt::test('sales.fulfillment.index')
            ->call('fulfill', $so)
            ->set('bin_id', $bin->id)
            ->call('confirmFulfillment')
            ->assertHasErrors(['bin_id']); // Confirming with this bin should fail validation due to low stock
    }

    /**
     * 7. Livewire components authorization bypass checks (Security/White-box)
     */
    public function test_sales_rep_cannot_delete_product()
    {
        $salesRep = User::factory()->create();
        $salesRep->assignRole('Sales Representative');
        $this->actingAs($salesRep);

        $product = Product::first();
        $this->assertNotNull($product);

        \Livewire\Volt\Volt::test('products.index')
            ->call('delete', $product->id)
            ->assertStatus(403);
    }

    public function test_sales_rep_cannot_delete_customer()
    {
        $salesRep = User::factory()->create();
        $salesRep->assignRole('Sales Representative');
        $this->actingAs($salesRep);

        $customer = Customer::first();
        $this->assertNotNull($customer);

        \Livewire\Volt\Volt::test('customers.index')
            ->call('delete', $customer->id)
            ->assertStatus(403);
    }

    public function test_sales_rep_cannot_access_suppliers_directory()
    {
        $salesRep = User::factory()->create();
        $salesRep->assignRole('Sales Representative');
        $this->actingAs($salesRep);

        \Livewire\Volt\Volt::test('suppliers.index')
            ->assertStatus(403);
    }

    public function test_sales_rep_cannot_access_warehouses_directory()
    {
        $salesRep = User::factory()->create();
        $salesRep->assignRole('Sales Representative');
        $this->actingAs($salesRep);

        \Livewire\Volt\Volt::test('warehouses.index')
            ->assertStatus(403);
    }
}
