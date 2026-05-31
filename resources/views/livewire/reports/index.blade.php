<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public $reportCategory = 'financial';
    public $reportType = 'tax_return';
    public $startDate;
    public $endDate;

    public function mount()
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
    }

    public function updatedReportCategory($value)
    {
        if ($value === 'financial') {
            $this->reportType = 'tax_return';
        } elseif ($value === 'procurement') {
            $this->reportType = 'purchase_order';
        } elseif ($value === 'sales') {
            $this->reportType = 'sales';
        } elseif ($value === 'inventory') {
            $this->reportType = 'inventory';
        }
        $this->resetPage();
    }

    public function updatedReportType()
    {
        $this->resetPage();
    }

    public function with()
    {
        $data = [];
        $queryStart = Carbon::parse($this->startDate)->startOfDay();
        $queryEnd = Carbon::parse($this->endDate)->endOfDay();

        if ($this->reportCategory === 'financial') {
            if ($this->reportType === 'tax_return' || $this->reportType === 'gst_vat') {
                $invoices = Invoice::whereBetween('issue_date', [$queryStart, $queryEnd])->get();
                $expenses = Expense::whereBetween('expense_date', [$queryStart, $queryEnd])->get();

                $salesTaxCollected = $invoices->sum('tax_amount');
                $purchaseTaxPaid = $expenses->sum('tax_amount');

                $data['taxData'] = [
                    'sales_tax_collected' => $salesTaxCollected,
                    'purchase_tax_paid' => $purchaseTaxPaid,
                    'net_tax_liability' => $salesTaxCollected - $purchaseTaxPaid,
                    'total_sales_revenue' => $invoices->sum('amount'),
                    'total_expenses' => $expenses->sum('amount')
                ];
            } elseif ($this->reportType === 'invoice') {
                $data['invoices'] = Invoice::with(['salesOrder.customer'])
                    ->whereBetween('issue_date', [$queryStart, $queryEnd])
                    ->orderBy('issue_date', 'desc')
                    ->paginate(15);
            }
        } elseif ($this->reportCategory === 'procurement') {
            if ($this->reportType === 'purchase_order') {
                $data['purchaseOrders'] = PurchaseOrder::with('supplier')
                    ->whereBetween('created_at', [$queryStart, $queryEnd])
                    ->orderBy('created_at', 'desc')
                    ->paginate(15);
            } elseif ($this->reportType === 'grn') {
                $data['grns'] = GoodsReceiptNote::with(['purchaseOrder.supplier', 'user'])
                    ->whereBetween('created_at', [$queryStart, $queryEnd])
                    ->orderBy('created_at', 'desc')
                    ->paginate(15);
            }
        } elseif ($this->reportCategory === 'sales') {
            if ($this->reportType === 'sales') {
                $data['salesOrders'] = SalesOrder::with('customer')
                    ->whereBetween('created_at', [$queryStart, $queryEnd])
                    ->orderBy('created_at', 'desc')
                    ->paginate(15);
            }
        } elseif ($this->reportCategory === 'inventory') {
            if ($this->reportType === 'inventory') {
                $data['products'] = Product::with('binStocks')->paginate(15);
            } elseif ($this->reportType === 'stock_movements') {
                $data['movements'] = InventoryTransaction::with(['product', 'user'])
                    ->whereBetween('created_at', [$queryStart, $queryEnd])
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
            }
        }

        return $data;
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Enterprise Reports</h1>
            <p class="text-gray-500 mt-1 text-sm">Comprehensive operational and financial data analysis</p>
        </div>
        <div class="flex items-center gap-3 bg-white p-2 rounded-xl border border-gray-200 shadow-sm">
            <input type="date" wire:model.live="startDate" class="border-none text-sm font-semibold text-gray-700 bg-transparent focus:ring-0">
            <span class="text-gray-400">&rarr;</span>
            <input type="date" wire:model.live="endDate" class="border-none text-sm font-semibold text-gray-700 bg-transparent focus:ring-0">
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Categories -->
            <div class="bg-white rounded-3xl p-4 shadow-sm border border-gray-150">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-wider mb-3 px-3">Report Category</h3>
                <nav class="space-y-1">
                    <button wire:click="$set('reportCategory', 'financial')" class="w-full flex items-center px-3 py-2 text-sm font-bold rounded-xl transition-colors {{ $reportCategory === 'financial' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50' }}">
                        Financial & Tax
                    </button>
                    <button wire:click="$set('reportCategory', 'procurement')" class="w-full flex items-center px-3 py-2 text-sm font-bold rounded-xl transition-colors {{ $reportCategory === 'procurement' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50' }}">
                        Procurement
                    </button>
                    <button wire:click="$set('reportCategory', 'sales')" class="w-full flex items-center px-3 py-2 text-sm font-bold rounded-xl transition-colors {{ $reportCategory === 'sales' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50' }}">
                        Sales
                    </button>
                    <button wire:click="$set('reportCategory', 'inventory')" class="w-full flex items-center px-3 py-2 text-sm font-bold rounded-xl transition-colors {{ $reportCategory === 'inventory' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50' }}">
                        Inventory
                    </button>
                </nav>
            </div>

            <!-- Sub Reports -->
            <div class="bg-white rounded-3xl p-4 shadow-sm border border-gray-150">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-wider mb-3 px-3">Available Reports</h3>
                <nav class="space-y-1">
                    @if($reportCategory === 'financial')
                        <button wire:click="$set('reportType', 'tax_return')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'tax_return' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">Tax Return Reports</button>
                        <button wire:click="$set('reportType', 'gst_vat')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'gst_vat' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">GST/VAT Reports</button>
                        <button wire:click="$set('reportType', 'invoice')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'invoice' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">Invoice Reports</button>
                    @elseif($reportCategory === 'procurement')
                        <button wire:click="$set('reportType', 'purchase_order')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'purchase_order' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">Purchase Order Reports</button>
                        <button wire:click="$set('reportType', 'grn')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'grn' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">GRN Reports</button>
                    @elseif($reportCategory === 'sales')
                        <button wire:click="$set('reportType', 'sales')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'sales' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">Sales Reports</button>
                    @elseif($reportCategory === 'inventory')
                        <button wire:click="$set('reportType', 'inventory')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'inventory' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">Inventory Reports</button>
                        <button wire:click="$set('reportType', 'stock_movements')" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-colors {{ $reportType === 'stock_movements' ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">Stock Movements</button>
                    @endif
                </nav>
            </div>
        </div>

        <!-- Report Content Area -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-150 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-900 capitalize">{{ str_replace('_', ' ', $reportType) }}</h2>
                    <button onclick="window.print()" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-lg transition-colors">
                        Print Report
                    </button>
                </div>
                
                <div class="p-6">
                    @if(in_array($reportType, ['tax_return', 'gst_vat']) && isset($taxData))
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-indigo-50 rounded-2xl p-6 border border-indigo-100">
                                <span class="text-indigo-600 text-xs font-black uppercase tracking-wider">Sales Tax Collected</span>
                                <h3 class="text-3xl font-black text-indigo-900 mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($taxData['sales_tax_collected'], 2) }}</h3>
                            </div>
                            <div class="bg-rose-50 rounded-2xl p-6 border border-rose-100">
                                <span class="text-rose-600 text-xs font-black uppercase tracking-wider">Purchase Tax Paid</span>
                                <h3 class="text-3xl font-black text-rose-900 mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($taxData['purchase_tax_paid'], 2) }}</h3>
                            </div>
                            <div class="bg-emerald-50 rounded-2xl p-6 border border-emerald-100">
                                <span class="text-emerald-600 text-xs font-black uppercase tracking-wider">Net Tax Liability</span>
                                <h3 class="text-3xl font-black text-emerald-900 mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($taxData['net_tax_liability'], 2) }}</h3>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600">
                            <p><strong>Total Sales Revenue:</strong> {{ setting('currency_symbol', '$') }}{{ number_format($taxData['total_sales_revenue'], 2) }}</p>
                            <p><strong>Total Purchase Expenses:</strong> {{ setting('currency_symbol', '$') }}{{ number_format($taxData['total_expenses'], 2) }}</p>
                        </div>

                    @elseif($reportType === 'invoice' && isset($invoices))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                                        <th class="py-3 px-4">Invoice #</th>
                                        <th class="py-3 px-4">Date</th>
                                        <th class="py-3 px-4">Customer</th>
                                        <th class="py-3 px-4 text-right">Tax</th>
                                        <th class="py-3 px-4 text-right">Total Amount</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($invoices as $inv)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 text-sm">
                                            <td class="py-3 px-4 font-bold text-indigo-600">INV-{{ str_pad($inv->id, 5, '0', STR_PAD_LEFT) }}</td>
                                            <td class="py-3 px-4">{{ Carbon\Carbon::parse($inv->issue_date)->format('M d, Y') }}</td>
                                            <td class="py-3 px-4">{{ $inv->salesOrder->customer->name ?? 'N/A' }}</td>
                                            <td class="py-3 px-4 text-right">{{ setting('currency_symbol', '$') }}{{ number_format($inv->tax_amount ?? 0, 2) }}</td>
                                            <td class="py-3 px-4 text-right font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($inv->amount, 2) }}</td>
                                            <td class="py-3 px-4 text-center"><span class="px-2 py-1 rounded bg-gray-100 text-xs">{{ ucfirst($inv->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-6 text-gray-400">No invoices found for this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="mt-4">{{ $invoices->links() }}</div>
                        </div>

                    @elseif($reportType === 'purchase_order' && isset($purchaseOrders))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                                        <th class="py-3 px-4">PO #</th>
                                        <th class="py-3 px-4">Date</th>
                                        <th class="py-3 px-4">Supplier</th>
                                        <th class="py-3 px-4 text-right">Total Amount</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($purchaseOrders as $po)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 text-sm">
                                            <td class="py-3 px-4 font-bold text-indigo-600">PO-{{ str_pad($po->id, 5, '0', STR_PAD_LEFT) }}</td>
                                            <td class="py-3 px-4">{{ $po->created_at->format('M d, Y') }}</td>
                                            <td class="py-3 px-4">{{ $po->supplier->name ?? 'N/A' }}</td>
                                            <td class="py-3 px-4 text-right font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($po->total_amount, 2) }}</td>
                                            <td class="py-3 px-4 text-center"><span class="px-2 py-1 rounded bg-gray-100 text-xs">{{ ucfirst($po->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-6 text-gray-400">No purchase orders found for this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="mt-4">{{ $purchaseOrders->links() }}</div>
                        </div>

                    @elseif($reportType === 'grn' && isset($grns))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                                        <th class="py-3 px-4">GRN #</th>
                                        <th class="py-3 px-4">Received Date</th>
                                        <th class="py-3 px-4">Purchase Order</th>
                                        <th class="py-3 px-4">Supplier</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($grns as $grn)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 text-sm">
                                            <td class="py-3 px-4 font-bold text-indigo-600">GRN-{{ str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}</td>
                                            <td class="py-3 px-4">{{ $grn->created_at->format('M d, Y') }}</td>
                                            <td class="py-3 px-4">PO-{{ str_pad($grn->purchase_order_id, 5, '0', STR_PAD_LEFT) }}</td>
                                            <td class="py-3 px-4">{{ $grn->purchaseOrder->supplier->name ?? 'N/A' }}</td>
                                            <td class="py-3 px-4 text-center"><span class="px-2 py-1 rounded bg-gray-100 text-xs">{{ ucfirst($grn->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-6 text-gray-400">No GRNs found for this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="mt-4">{{ $grns->links() }}</div>
                        </div>

                    @elseif($reportType === 'sales' && isset($salesOrders))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                                        <th class="py-3 px-4">SO #</th>
                                        <th class="py-3 px-4">Date</th>
                                        <th class="py-3 px-4">Customer</th>
                                        <th class="py-3 px-4 text-right">Total Amount</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($salesOrders as $so)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 text-sm">
                                            <td class="py-3 px-4 font-bold text-indigo-600">SO-{{ str_pad($so->id, 5, '0', STR_PAD_LEFT) }}</td>
                                            <td class="py-3 px-4">{{ $so->created_at->format('M d, Y') }}</td>
                                            <td class="py-3 px-4">{{ $so->customer->name ?? 'N/A' }}</td>
                                            <td class="py-3 px-4 text-right font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($so->total_amount, 2) }}</td>
                                            <td class="py-3 px-4 text-center"><span class="px-2 py-1 rounded bg-gray-100 text-xs">{{ ucfirst($so->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-6 text-gray-400">No sales orders found for this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="mt-4">{{ $salesOrders->links() }}</div>
                        </div>

                    @elseif($reportType === 'inventory' && isset($products))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                                        <th class="py-3 px-4">Product / SKU</th>
                                        <th class="py-3 px-4 text-right">Unit Cost</th>
                                        <th class="py-3 px-4 text-right">Total Stock</th>
                                        <th class="py-3 px-4 text-right">Valuation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($products as $product)
                                        @php
                                            $totalStock = $product->binStocks->sum('quantity');
                                            $valuation = $totalStock * $product->unit_price;
                                        @endphp
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 text-sm">
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-gray-900">{{ $product->name }}</div>
                                                <div class="text-xs text-gray-500 font-mono">{{ $product->sku }}</div>
                                            </td>
                                            <td class="py-3 px-4 text-right">{{ setting('currency_symbol', '$') }}{{ number_format($product->unit_price, 2) }}</td>
                                            <td class="py-3 px-4 text-right font-bold">{{ $totalStock }}</td>
                                            <td class="py-3 px-4 text-right font-bold text-indigo-600">{{ setting('currency_symbol', '$') }}{{ number_format($valuation, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-6 text-gray-400">No products found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="mt-4">{{ $products->links() }}</div>
                        </div>

                    @elseif($reportType === 'stock_movements' && isset($movements))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                                        <th class="py-3 px-4">Date</th>
                                        <th class="py-3 px-4">Product</th>
                                        <th class="py-3 px-4 text-center">Type</th>
                                        <th class="py-3 px-4 text-right">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($movements as $tx)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 text-sm">
                                            <td class="py-3 px-4">{{ $tx->created_at->format('M d, Y H:i') }}</td>
                                            <td class="py-3 px-4 font-semibold">{{ $tx->product->name ?? 'N/A' }}</td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="px-2 py-1 rounded text-xs {{ $tx->type === 'IN' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                    {{ $tx->type }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right font-bold">{{ $tx->quantity }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-6 text-gray-400">No movements found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="mt-4">{{ $movements->links() }}</div>
                        </div>

                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
