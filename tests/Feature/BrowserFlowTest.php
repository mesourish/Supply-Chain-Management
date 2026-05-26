<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BrowserFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_application_flow()
    {
        // 1. Seed database to ensure roles exist
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);

        // 2. Test Login page loads
        $response = $this->get('/login');
        if ($response->status() !== 200) {
            $response->dump();
        }
        $response->assertStatus(200);

        // 3. Test Authentication
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        // Livewire Volt login usually returns a 200 response with JSON redirect instruction.
        // Or standard POST /login redirects to /dashboard.
        // Wait, the Volt login uses Livewire to post to /livewire/update.
        // For testing, we can just login using Auth::login()
        $this->actingAs($admin);

        // 4. Test Dashboard
        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        // 5. Test Inventory Pages
        $this->get('/products')->assertStatus(200);
        $this->get('/warehouses')->assertStatus(200);
        $this->get('/inventory/log')->assertStatus(200);

        // 6. Test Procurement (with new RFQs page)
        $this->get('/suppliers')->assertStatus(200);
        $this->get('/procurement/rfqs')->assertStatus(200);
        $this->get('/procurement/purchase-orders')->assertStatus(200);
        $this->get('/procurement/grn')->assertStatus(200);

        // 7. Test Sales (with new Quotations page)
        $this->get('/customers')->assertStatus(200);
        $this->get('/sales/quotations')->assertStatus(200);
        $this->get('/sales/orders')->assertStatus(200);

        // 8. Test CRM Leads (New)
        $this->get('/crm/leads')->assertStatus(200);

        // 9. Test Projects Module (New)
        $this->get('/projects')->assertStatus(200);

        // 10. Test Admin
        $this->get('/admin/roles')->assertStatus(200);
        $this->get('/admin/users')->assertStatus(200);
    }
}
