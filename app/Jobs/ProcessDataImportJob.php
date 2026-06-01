<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\DataImport;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessDataImportJob implements ShouldQueue
{
    use Queueable;

    public $import;

    /**
     * Create a new job instance.
     */
    public function __construct(DataImport $import)
    {
        $this->import = $import;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $import = $this->import;
        $import->update(['status' => 'processing']);

        $filePath = Storage::disk('local')->path($import->file_path);
        if (!file_exists($filePath)) {
            $import->update([
                'status' => 'failed',
                'errors' => ['File not found on local storage disk']
            ]);
            return;
        }

        $file = fopen($filePath, 'r');
        if (!$file) {
            $import->update([
                'status' => 'failed',
                'errors' => ['Failed to open file stream']
            ]);
            return;
        }

        $headers = fgetcsv($file);
        if (!$headers) {
            $import->update([
                'status' => 'failed',
                'errors' => ['Empty or corrupt CSV template structure']
            ]);
            fclose($file);
            return;
        }

        // Clean headers
        $headers = array_map(function($h) {
            return trim(strtolower(str_replace([' ', '-'], '_', $h)));
        }, $headers);

        $totalRows = 0;
        $processed = 0;
        $failed = 0;
        $errors = [];

        // First pass: count lines
        $totalRows = 0;
        while (($row = fgetcsv($file)) !== false) {
            if (empty(array_filter($row))) continue;
            $totalRows++;
        }
        $import->update(['total_rows' => $totalRows]);

        // Rewind and skip header
        rewind($file);
        fgetcsv($file);

        while (($row = fgetcsv($file)) !== false) {
            if (empty(array_filter($row))) continue;

            $data = array_combine(
                array_slice($headers, 0, count($row)),
                array_slice($row, 0, count($headers))
            );

            try {
                if ($import->type === 'supplier') {
                    if (empty($data['name'])) {
                        throw new \Exception('Supplier name column is mandatory');
                    }
                    Supplier::create([
                        'name' => $data['name'],
                        'email' => $data['email'] ?? null,
                        'contact_person' => $data['contact_person'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'tax_id' => $data['tax_id'] ?? null,
                        'address' => $data['address'] ?? null,
                        'is_active' => true,
                    ]);
                } elseif ($import->type === 'product') {
                    if (empty($data['sku']) || empty($data['name'])) {
                        throw new \Exception('Product SKU and name columns are mandatory');
                    }
                    Product::create([
                        'sku' => $data['sku'],
                        'name' => $data['name'],
                        'barcode' => $data['barcode'] ?? '88' . rand(1000000000, 9999999999),
                        'category' => $data['category'] ?? 'General',
                        'brand' => $data['brand'] ?? null,
                        'unit_of_measure' => $data['unit_of_measure'] ?? 'pcs',
                        'cost_price' => floatval($data['cost_price'] ?? 0.0),
                        'unit_price' => floatval($data['unit_price'] ?? 0.0),
                        'reorder_level' => intval($data['reorder_level'] ?? 10),
                    ]);
                } elseif ($import->type === 'customer') {
                    if (empty($data['name'])) {
                        throw new \Exception('Customer name column is mandatory');
                    }
                    Customer::create([
                        'name' => $data['name'],
                        'email' => $data['email'] ?? null,
                        'contact_person' => $data['contact_person'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'tax_id' => $data['tax_id'] ?? null,
                        'billing_address' => $data['billing_address'] ?? null,
                        'shipping_address' => $data['shipping_address'] ?? null,
                    ]);
                }
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Row " . ($processed + $failed) . ": " . $e->getMessage();
            }

            // Periodically update progress
            if (($processed + $failed) % 5 === 0) {
                $import->update([
                    'processed_rows' => $processed,
                    'failed_rows' => $failed,
                    'errors' => array_slice($errors, 0, 50), // Cap visible error messages to 50 items
                ]);
            }
        }

        fclose($file);

        $import->update([
            'status' => $failed > 0 ? ($processed > 0 ? 'completed' : 'failed') : 'completed',
            'processed_rows' => $processed,
            'failed_rows' => $failed,
            'errors' => $errors,
        ]);
    }
}
