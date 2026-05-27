<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_module_actions_are_visible()
    {
        // 1. Seed database to ensure roles and invoices exist
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);

        // 2. Authenticate as Super Admin
        $this->actingAs($admin);

        // 3. Visit finance/invoices route
        $response = $this->get('/finance/invoices');
        $response->assertStatus(200);

        // 4. Assert key titles are present
        $response->assertSee('Invoices &amp; Billing', false);
        $response->assertSee('Total Billing Volume');
        $response->assertSee('Paid Accounts Receipts');

        // 5. Assert database records are present
        $invoices = Invoice::all();
        $this->assertGreaterThan(0, $invoices->count());

        // 6. Assert table columns and values are rendered
        foreach ($invoices as $invoice) {
            $response->assertSee('INV-#' . $invoice->id);
            // Assert that action buttons exist
            $response->assertSee('wire:click="viewInvoice(' . $invoice->id . ')"', false);
            $response->assertSee('wire:click="editInvoice(' . $invoice->id . ')"', false);
            $response->assertSee('wire:click="changeStatus(' . $invoice->id . ')"', false);
            $response->assertSee('wire:click="deleteInvoice(' . $invoice->id . ')"', false);
        }
    }
}
