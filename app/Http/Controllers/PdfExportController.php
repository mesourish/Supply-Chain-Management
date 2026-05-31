<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExportController extends Controller
{
    public function invoice(Invoice $invoice)
    {
        $invoice->load(['customer', 'salesOrder.customer', 'items.product']);
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.document', ['type' => 'Invoice', 'model' => $invoice]);
        return $pdf->download('invoice-' . $invoice->id . '.pdf');
    }

    public function salesOrder(SalesOrder $order)
    {
        $order->load(['customer', 'items.product']);
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.document', ['type' => 'Sales Order', 'model' => $order]);
        return $pdf->download('sales-order-' . $order->id . '.pdf');
    }

    public function purchaseOrder(PurchaseOrder $order)
    {
        $order->load(['supplier', 'items.product']);
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.document', ['type' => 'Purchase Order', 'model' => $order]);
        return $pdf->download('purchase-order-' . $order->id . '.pdf');
    }
}
