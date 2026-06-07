<?php

namespace App\Observers;

use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\ManufacturingOrder;
use App\Models\BillOfMaterial;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use App\Helpers\SystemLogger;

class SalesOrderObserver
{
    /**
     * Handle the SalesOrder "updated" event.
     */
    public function updated(SalesOrder $salesOrder): void
    {
        if ($salesOrder->isDirty('status') && $salesOrder->status === 'processing') {
            $this->triggerWorkflowBranching($salesOrder);
        }
    }

    /**
     * Handle the SalesOrder "created" event.
     */
    public function created(SalesOrder $salesOrder): void
    {
        if ($salesOrder->status === 'processing') {
            $this->triggerWorkflowBranching($salesOrder);
        }
    }

    /**
     * Executes the branching triggers inside a transaction.
     */
    protected function triggerWorkflowBranching(SalesOrder $salesOrder): void
    {
        DB::transaction(function () use ($salesOrder) {
            $salesOrder->load('items.product');

            foreach ($salesOrder->items as $item) {
                $product = $item->product;
                if (!$product) continue;

                // 1. Route: manufacture (Make-to-Order MO trigger)
                if ($product->route === 'manufacture' || $product->route === 'make_to_order') {
                    $bom = BillOfMaterial::where('product_id', $product->id)->first();
                    if ($bom) {
                        $moExists = ManufacturingOrder::where('sales_order_id', $salesOrder->id)
                            ->where('product_id', $product->id)
                            ->exists();

                        if (!$moExists) {
                            $nextId = ManufacturingOrder::max('id') + 1;
                            $moNumber = 'MO-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
                            
                            ManufacturingOrder::create([
                                'mo_number' => $moNumber,
                                'product_id' => $product->id,
                                'bom_id' => $bom->id,
                                'quantity_to_produce' => $item->quantity,
                                'status' => 'draft',
                                'sales_order_id' => $salesOrder->id,
                                'scheduled_start_date' => now()->addDays(2),
                            ]);

                            SystemLogger::log(
                                'mo_auto_created',
                                'Manufacturing',
                                "Auto-generated Manufacturing Order {$moNumber} for Product {$product->sku} from Sales Order SO-" . str_pad($salesOrder->id, 5, '0', STR_PAD_LEFT)
                            );
                        }
                    }
                }

                // 2. Route: buy (Make-to-Order PO trigger)
                if ($product->route === 'buy' || $product->route === 'buy_and_sell') {
                    // Check if a PO already exists for this SO item to avoid duplicates
                    $soSuffix = 'SO-' . str_pad($salesOrder->id, 5, '0', STR_PAD_LEFT);
                    $poExists = PurchaseOrder::where('remarks', 'like', '%' . $soSuffix . '%')
                        ->whereHas('items', function ($query) use ($product) {
                            $query->where('product_id', $product->id);
                        })->exists();

                    if (!$poExists) {
                        $supplier = $product->suppliers()->first();
                        if ($supplier) {
                            $contact = $supplier->contactPersons()->where('is_primary', true)->first();
                            $billing = $supplier->addresses()->where('type', 'billing')->first();
                            $shipping = $supplier->addresses()->where('type', 'shipping')->first();

                            $po = PurchaseOrder::create([
                                'supplier_id' => $supplier->id,
                                'status' => 'draft',
                                'approval_status' => 'pending_approval',
                                'currency_code' => $supplier->currency_code ?? 'USD',
                                'exchange_rate' => 1.0,
                                'remarks' => "Auto-generated for Sales Order {$soSuffix}",
                                'contact_person_id' => $contact ? $contact->id : null,
                                'billing_address_id' => $billing ? $billing->id : null,
                                'shipping_address_id' => $shipping ? $shipping->id : null,
                                'expected_delivery' => now()->addDays($product->lead_time_days ?? 7),
                                'subtotal' => 0,
                                'gst_type' => 'exclusive',
                                'gst_percentage' => 0,
                                'gst_amount' => 0,
                                'total_amount' => 0,
                            ]);

                            // Resolve PO line unit price
                            $unitPrice = $product->suppliers()
                                ->where('supplier_id', $supplier->id)
                                ->first()
                                ->pivot
                                ->price ?? $product->cost_price;

                            $po->items()->create([
                                'product_id' => $product->id,
                                'quantity' => $item->quantity,
                                'unit_price' => $unitPrice,
                            ]);

                            // Recalculate and update PO totals
                            $subtotal = $item->quantity * $unitPrice;
                            $po->update([
                                'subtotal' => $subtotal,
                                'total_amount' => $subtotal,
                            ]);

                            SystemLogger::log(
                                'po_auto_created',
                                'Procurement',
                                "Auto-generated Purchase Order PO-" . str_pad($po->id, 5, '0', STR_PAD_LEFT) . " for Product {$product->sku} from Sales Order {$soSuffix}"
                            );
                        }
                    }
                }
            }
        });
    }
}
