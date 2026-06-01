<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\DataImport;
use App\Jobs\ProcessDataImportJob;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads, WithPagination;

    public $type = 'supplier';
    public $file;

    public function rules()
    {
        return [
            'type' => 'required|in:supplier,product,customer',
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:51200', // max 50MB
        ];
    }

    public function uploadFile()
    {
        $this->validate();

        $path = $this->file->store('imports', 'local');

        $import = DataImport::create([
            'type' => $this->type,
            'file_path' => $path,
            'status' => 'pending',
            'total_rows' => 0,
        ]);

        ProcessDataImportJob::dispatch($import);

        $this->reset('file');
        $this->dispatch('toast', type: 'success', message: 'Import queued successfully. Please wait while it is processed in the background.');
    }

    public function downloadTemplate($entity)
    {
        $headers = [];
        if ($entity === 'supplier') {
            $headers = ['name', 'email', 'contact_person', 'phone', 'tax_id', 'address'];
        } elseif ($entity === 'product') {
            $headers = ['sku', 'name', 'barcode', 'category', 'brand', 'unit_of_measure', 'cost_price', 'unit_price', 'reorder_level'];
        } elseif ($entity === 'customer') {
            $headers = ['name', 'email', 'contact_person', 'phone', 'tax_id', 'billing_address', 'shipping_address'];
        } else {
            return;
        }

        $filename = "{$entity}_template.csv";
        
        $callback = function() use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fclose($file);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function with()
    {
        return [
            'imports' => DataImport::latest()->paginate(10),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8" wire:poll.5s>
    <div class="mb-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 border-b border-gray-200">
            <h2 class="text-2xl font-semibold mb-2">Bulk Data Import</h2>
            <p class="text-gray-600 mb-6">Upload thousands of records easily using Excel or CSV files. Processing happens in the background to prevent timeouts.</p>

            <form wire:submit="uploadFile">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="type" value="Data Type" />
                        <select wire:model="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="supplier">Suppliers</option>
                            <option value="product">Products</option>
                            <option value="customer">Customers (Clients)</option>
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        
                        <div class="mt-4">
                            <span class="text-sm text-gray-500">Need a template?</span>
                            <div class="flex gap-2 mt-1">
                                <button type="button" wire:click="downloadTemplate('supplier')" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Supplier Template</button>
                                <span class="text-gray-300">|</span>
                                <button type="button" wire:click="downloadTemplate('product')" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Product Template</button>
                                <span class="text-gray-300">|</span>
                                <button type="button" wire:click="downloadTemplate('customer')" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Customer Template</button>
                            </div>
                        </div>
                    </div>

                    <div x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <x-input-label for="file" value="Upload File (.csv, .xlsx)" />
                        <div class="mt-1 flex items-center">
                            <input wire:model="file" type="file" id="file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" accept=".csv, .xlsx, .xls" required />
                        </div>
                        
                        <!-- Upload Progress Bar -->
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Uploading to server...</span>
                                <span x-text="progress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300" :style="`width: ${progress}%`"></div>
                            </div>
                        </div>

                        <div wire:loading wire:target="uploadFile" class="text-sm text-indigo-600 mt-2">Queuing import...</div>
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6">
                    <x-primary-button wire:loading.attr="disabled">
                        Queue Import
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <!-- History Table -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Import History & Status (Auto-refreshes)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Errors</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($imports as $import)
                            @php
                                $total = $import->total_rows > 0 ? $import->total_rows : 1; // Prevent div zero
                                $current = $import->processed_rows + $import->failed_rows;
                                $percentage = min(100, round(($current / $total) * 100));
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $import->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="capitalize text-sm font-medium text-gray-900">{{ $import->type }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($import->status === 'pending')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Pending</span>
                                    @elseif($import->status === 'processing')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Processing</span>
                                    @elseif($import->status === 'completed')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                            <div class="bg-indigo-600 h-2.5 rounded-full" style="width: {{ $percentage }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ $current }}/{{ $import->total_rows }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($import->failed_rows > 0)
                                        <span class="text-red-600 font-medium">{{ $import->failed_rows }} failed</span>
                                    @else
                                        <span class="text-green-600">0</span>
                                    @endif
                                    
                                    @if($import->errors && count($import->errors) > 0)
                                        <div class="mt-1 text-xs text-gray-400 max-w-xs truncate" title="{{ json_encode($import->errors) }}">
                                            Hover to see errors
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $import->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No bulk imports yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $imports->links() }}
            </div>
        </div>
    </div>
</div>
