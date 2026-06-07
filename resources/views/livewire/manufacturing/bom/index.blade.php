<?php

use Livewire\Volt\Component;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showCreateModal = false;
    public $showEditModal = false;
    
    // Form fields
    public $bom_id = null;
    public $product_id = '';
    public $bom_code = '';
    public $name = '';
    public $output_quantity = 1.0000;
    public $components = []; // Array of ['product_id' => '', 'quantity' => 1.0000]

    protected $listeners = ['refresh' => '$refresh'];

    public function mount()
    {
        if (!Auth::user()->can('view manufacturing')) {
            abort(403);
        }
    }

    public function openCreateModal()
    {
        $this->resetForm();
        
        // Autogenerate BOM code
        $nextId = BillOfMaterial::max('id') + 1;
        $this->bom_code = 'BOM-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        
        $this->components = [
            ['product_id' => '', 'quantity' => 1.0000]
        ];
        $this->showCreateModal = true;
    }

    public function addComponentRow()
    {
        $this->components[] = ['product_id' => '', 'quantity' => 1.0000];
    }

    public function removeComponentRow($index)
    {
        unset($this->components[$index]);
        $this->components = array_values($this->components);
    }

    public function resetForm()
    {
        $this->bom_id = null;
        $this->product_id = '';
        $this->bom_code = '';
        $this->name = '';
        $this->output_quantity = 1.0000;
        $this->components = [];
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->resetForm();
    }

    public function saveBOM()
    {
        if (!Auth::user()->can('manage manufacturing')) {
            abort(403);
        }

        $this->validate([
            'product_id' => 'required|exists:products,id',
            'bom_code' => 'required|string|unique:bills_of_materials,bom_code,' . ($this->bom_id ?? 'NULL'),
            'name' => 'required|string|max:255',
            'output_quantity' => 'required|numeric|min:0.0001',
            'components' => 'required|array|min:1',
            'components.*.product_id' => 'required|exists:products,id',
            'components.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () {
            $bom = BillOfMaterial::updateOrCreate(
                ['id' => $this->bom_id],
                [
                    'product_id' => $this->product_id,
                    'bom_code' => $this->bom_code,
                    'name' => $this->name,
                    'output_quantity' => $this->output_quantity,
                ]
            );

            // Sync component items
            $bom->items()->delete();
            foreach ($this->components as $comp) {
                BomItem::create([
                    'bom_id' => $bom->id,
                    'component_product_id' => $comp['product_id'],
                    'quantity_required' => $comp['quantity'],
                ]);
            }
        });

        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Bill of Materials saved successfully.');
    }

    public function editBOM($id)
    {
        $bom = BillOfMaterial::with('items')->findOrFail($id);
        $this->bom_id = $bom->id;
        $this->product_id = $bom->product_id;
        $this->bom_code = $bom->bom_code;
        $this->name = $bom->name;
        $this->output_quantity = $bom->output_quantity;
        
        $this->components = [];
        foreach ($bom->items as $item) {
            $this->components[] = [
                'product_id' => $item->component_product_id,
                'quantity' => $item->quantity_required,
            ];
        }
        
        $this->showEditModal = true;
    }

    public function deleteBOM($id)
    {
        if (!Auth::user()->can('manage manufacturing')) {
            abort(403);
        }

        $bom = BillOfMaterial::findOrFail($id);
        
        // Prevent deletion if linked to active MOs
        $hasActiveMO = \App\Models\ManufacturingOrder::where('bom_id', $bom->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();
            
        if ($hasActiveMO) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete BOM linked to active Manufacturing Orders.');
            return;
        }

        $bom->delete();
        $this->dispatch('toast', type: 'success', message: 'Bill of Materials deleted successfully.');
    }

    public function rendering($view)
    {
        $query = BillOfMaterial::with(['product', 'items.product']);
        
        if (trim($this->search)) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('bom_code', 'like', '%' . $this->search . '%')
                  ->orWhereHas('product', function($pq) {
                      $pq->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('sku', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Support both manufactured route and all products
        return $view->with([
            'boms' => $query->latest()->paginate(10),
            'manufacturedProducts' => Product::whereIn('route', ['manufacture', 'make_to_order'])->orderBy('name')->get(),
            'allProducts' => Product::orderBy('name')->get(),
        ]);
    }
}; ?>

<div class="p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Bills of Materials (BOM)</h1>
                <p class="text-sm text-slate-500 mt-1">Manage product assembly recipes, raw material consumption guidelines, and manufacturing lines.</p>
            </div>
            @can('manage manufacturing')
            <button wire:click="openCreateModal" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm shadow-md shadow-indigo-600/10 hover:shadow-indigo-600/25 transition">
                Create BOM Recipe
            </button>
            @endcan
        </div>

        <!-- Search / Filters -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search BOM code, finished product..." class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
            </div>
        </div>

        <!-- BOM Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-55/60">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">BOM Code</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Recipe Name</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Finished Good (Output)</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Output Qty</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Components List</th>
                            <th class="px-6 py-4 text-right font-semibold text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($boms as $bom)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-bold text-slate-800">{{ $bom->bom_code }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-700">{{ $bom->name }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-indigo-700">{{ $bom->product->name }}</div>
                                    <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $bom->product->sku }}</div>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-600">
                                    {{ number_format($bom->output_quantity, 4) }} units
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1.5 max-w-sm">
                                        @foreach($bom->items as $item)
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 bg-slate-100 text-slate-600 rounded-lg">
                                                {{ $item->product->name }}: <b class="text-slate-800">{{ number_format($item->quantity_required, 2) }}</b>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    @can('manage manufacturing')
                                        <button wire:click="editBOM({{ $bom->id }})" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                                            Edit
                                        </button>
                                        <button wire:click="deleteBOM({{ $bom->id }})" wire:confirm="Are you sure you want to delete this BOM recipe?" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl text-xs transition">
                                            Delete
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">Read-Only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500">No BOM recipes defined yet. Click "Create BOM Recipe" to start.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $boms->links() }}
            </div>
        </div>
    </div>

    <!-- BOM Create/Edit Modal -->
    @if($showCreateModal || $showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showCreateModal', false); $set('showEditModal', false)"></div>

                <div class="inline-block w-full max-w-3xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl relative z-50">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">
                        {{ $bom_id ? 'Edit Bill of Materials Recipe' : 'Create Bill of Materials Recipe' }}
                    </h3>
                    <p class="text-xs text-slate-400 mb-4">Define a finished good product and the components required to assemble it.</p>
                    
                    <form wire:submit="saveBOM" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">BOM Code</label>
                                <input type="text" wire:model="bom_code" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="e.g. BOM-00001">
                                @error('bom_code') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Recipe Name</label>
                                <input type="text" wire:model="name" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="e.g. Standard Wooden Desk Assembly">
                                @error('name') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Finished Good Output Product</label>
                                <select wire:model="product_id" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                                    <option value="">Select Finished Product...</option>
                                    @foreach($allProducts as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }}) [Route: {{ $p->route }}]</option>
                                    @endforeach
                                </select>
                                @error('product_id') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Output Quantity</label>
                                <input type="number" step="0.0001" wire:model="output_quantity" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                                @error('output_quantity') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Component Line Items -->
                        <div class="border-t border-slate-100 pt-4 mt-6">
                            <div class="flex justify-between items-center mb-3">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Required Component Materials</label>
                                <button type="button" wire:click="addComponentRow" class="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold rounded-lg text-xs transition">
                                    + Add Component Row
                                </button>
                            </div>
                            
                            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                                @foreach($components as $index => $comp)
                                    <div class="flex gap-3 items-center">
                                        <div class="flex-1">
                                            <select wire:model="components.{{ $index }}.product_id" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                                                <option value="">Select Component Product...</option>
                                                @foreach($allProducts as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                                @endforeach
                                            </select>
                                            @error('components.'.$index.'.product_id') <span class="text-xs text-rose-600 font-bold mt-1 block">Product is required.</span> @enderror
                                        </div>
                                        <div class="w-40">
                                            <input type="number" step="0.0001" wire:model="components.{{ $index }}.quantity" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="Qty Required">
                                            @error('components.'.$index.'.quantity') <span class="text-xs text-rose-600 font-bold mt-1 block">Qty required.</span> @enderror
                                        </div>
                                        <div>
                                            <button type="button" wire:click="removeComponentRow({{ $index }})" class="p-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('components') <span class="text-xs text-rose-600 font-bold mt-2 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" wire:click="closeModal" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                            <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition">Save BOM Recipe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
