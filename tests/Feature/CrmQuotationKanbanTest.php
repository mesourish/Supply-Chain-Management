<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CrmLead;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmQuotationKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    public function test_crm_leads_action_center_and_details()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // 1. Assert leads page loads successfully
        $response = $this->get('/crm/leads');
        $response->assertStatus(200);

        // 2. Create dummy lead
        $lead = CrmLead::create([
            'title' => 'Big Enterprise Supply Order',
            'company_name' => 'Acme Corp',
            'contact_name' => 'John Doe',
            'email' => 'john@acme.example.com',
            'phone' => '1234567890',
            'deal_value' => 50000.00,
            'pipeline_stage' => 'new',
            'deal_probability' => 10,
            'assigned_user_id' => $admin->id,
        ]);

        // 3. Test Livewire component
        Livewire::test('crm.leads')
            ->call('viewLead', $lead->id)
            ->assertSet('showDetailsModal', true)
            ->assertSet('selectedLeadId', $lead->id)
            ->set('editTitle', 'Super Massive Order')
            ->set('editDealValue', 75000.00)
            ->set('editPipelineStage', 'proposal')
            ->call('updateLeadDetails')
            ->assertHasNoErrors()
            ->assertSet('isEditingDetails', false);

        $this->assertEquals('Super Massive Order', $lead->refresh()->title);
        $this->assertEquals(75000.00, $lead->deal_value);

        // 4. Test delete lead
        Livewire::test('crm.leads')
            ->call('deleteLead', $lead->id);

        $this->assertNull(CrmLead::find($lead->id));
    }

    public function test_quotations_kanban_and_actions()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // 1. Assert quotations page loads successfully
        $response = $this->get('/sales/quotations');
        $response->assertStatus(200);

        // 2. Create customer and product
        $customer = Customer::first();
        $product = Product::first();

        // 3. Test Quotations Livewire Component
        $quote = Quotation::create([
            'reference_no' => 'QTE-TEST-001',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'valid_until' => now()->addDays(30)->toDateString(),
            'total_amount' => 1000.00,
        ]);

        // 4. Test drag-and-drop / manual stage conversion
        Livewire::test('sales.quotations')
            ->assertSet('viewMode', 'table')
            ->set('viewMode', 'kanban')
            ->call('moveQuotationStatus', $quote->id, 'sent')
            ->assertSet('viewMode', 'kanban');

        $this->assertEquals('sent', $quote->refresh()->status);

        // 5. Test Quotation view and edit
        Livewire::test('sales.quotations')
            ->call('viewQuote', $quote->id)
            ->assertSet('showDetailsModal', true)
            ->assertSet('selectedQuoteId', $quote->id)
            ->call('editQuote', $quote->id)
            ->assertSet('isEditing', true)
            ->assertSet('editQuoteId', $quote->id)
            ->set('notes', 'Special SCM corporate terms')
            ->set('items', [
                ['product_id' => $product->id, 'description' => '', 'quantity' => 10, 'unit_price' => 150.00, 'is_blank' => false]
            ])
            ->call('saveQuote')
            ->assertHasNoErrors();

        $this->assertEquals('Special SCM corporate terms', $quote->refresh()->notes);
        $this->assertEquals(1500.00, $quote->refresh()->total_amount);

        // 6. Test delete quotation
        Livewire::test('sales.quotations')
            ->call('deleteQuote', $quote->id);

        $this->assertNull(Quotation::find($quote->id));
    }
}
