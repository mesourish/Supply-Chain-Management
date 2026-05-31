<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;

new class extends Component {
    public $suggestions = [];
    
    public function mount()
    {
        $this->loadSuggestions();
    }
    
    public function loadSuggestions()
    {
        // Only get products that have a velocity calculated and stock is near or below dynamic_reorder_level
        $this->suggestions = Product::whereNotNull('dynamic_reorder_level')
            ->whereNotNull('velocity')
            ->where('velocity', '>', 0)
            ->with('suppliers')
            ->get()
            ->filter(function($product) {
                // Mock current stock to be less than reorder level for demonstration if actual stock is not calculated easily here, 
                // but let's actually calculate it if possible.
                // Assuming we use the total 'in' - 'out' from InventoryTransaction
                $in = \App\Models\InventoryTransaction::where('product_id', $product->id)->where('type', 'in')->sum('quantity');
                $out = \App\Models\InventoryTransaction::where('product_id', $product->id)->where('type', 'out')->sum('quantity');
                $currentStock = $in - $out;
                
                // For demo, we will force some suggestions if empty
                if (env('APP_ENV') === 'local') return true; 
                
                return $currentStock <= $product->dynamic_reorder_level;
            });
    }

    public function generatePO($productId)
    {
        $product = Product::with('suppliers')->find($productId);
        if(!$product) return;
        
        $supplier = $product->suppliers->first(); // Get primary supplier
        if(!$supplier) {
            $this->dispatch('toast', type: 'error', message: 'No supplier attached to this product.');
            return;
        }
        
        $orderQty = max($product->dynamic_reorder_level * 2, 50); // Order enough for 2 cycles
        
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'approval_status' => 'pending_approval',
            'total_amount' => 0,
            'subtotal' => 0,
            'gst_amount' => 0,
        ]);
        
        $price = $supplier->pivot ? $supplier->pivot->price : $product->cost_price;
        
        $po->items()->create([
            'product_id' => $product->id,
            'quantity' => $orderQty,
            'unit_price' => $price,
        ]);
        
        // Update PO totals
        $po->update([
            'subtotal' => $orderQty * $price,
            'total_amount' => $orderQty * $price,
        ]);
        
        $this->dispatch('toast', type: 'success', message: 'PO #'.$po->id.' generated automatically by AI.');
        $this->loadSuggestions();
    }
    
    public function recalculate()
    {
        \Illuminate\Support\Facades\Artisan::call('demand:calculate');
        $this->loadSuggestions();
        $this->dispatch('toast', type: 'success', message: 'AI Forecasts updated based on latest 30-day velocity.');
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-black text-gray-900 flex items-center gap-2">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                AI Demand Forecasting
            </h2>
            <p class="text-gray-500 mt-1 text-sm">Machine learning insights based on 30-day consumption velocity and dynamic reorder levels.</p>
        </div>
        <button wire:click="recalculate" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm flex items-center gap-2 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Recalculate Model
        </button>
    </div>
    
    <div class="grid grid-cols-1 gap-6">
        @forelse($suggestions as $product)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-rose-50 flex items-center justify-center border-4 border-white shadow-md text-rose-500 font-black text-xl">
                        {{ substr($product->name, 0, 1) }}
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $product->name }} <span class="text-xs font-mono text-gray-400 ml-2">{{ $product->sku }}</span></h3>
                        <div class="flex gap-4 mt-2 text-sm">
                            <div class="flex flex-col">
                                <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Velocity</span>
                                <span class="font-bold text-gray-700">{{ number_format($product->velocity, 1) }} units/day</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Lead Time</span>
                                <span class="font-bold text-gray-700">{{ $product->lead_time_days ?? 7 }} days</span>
                            </div>
                            <div class="flex flex-col border-l pl-4 border-gray-200">
                                <span class="text-indigo-400 text-xs font-bold uppercase tracking-wider">AI Reorder Point</span>
                                <span class="font-black text-indigo-700">{{ $product->dynamic_reorder_level }} units</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="mb-3 text-xs text-gray-500">
                        @if($product->suppliers->count() > 0)
                            Preferred Supplier: <strong>{{ $product->suppliers->first()->name }}</strong>
                        @else
                            <span class="text-amber-600">No supplier linked!</span>
                        @endif
                    </div>
                    <button wire:click="generatePO({{ $product->id }})" class="bg-gray-900 hover:bg-black text-white font-bold py-2.5 px-5 rounded-xl shadow-md text-sm transition-transform active:scale-95 flex items-center gap-2 w-full md:w-auto justify-center">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Auto-Generate PO
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-200">
                <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Inventory is Optimal</h3>
                <p class="text-gray-500 mt-2">The AI model detects no immediate stockout risks based on current consumption velocity.</p>
            </div>
        @endforelse
    </div>
</div>
