<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExportController extends Controller
{
    public function invoice(Invoice $invoice)
    {
        $invoice->load(['customer', 'salesOrder.customer', 'items.product']);
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.document', ['type' => 'Invoice', 'model' => $invoice]);
        return $pdf->stream('invoice-' . $invoice->id . '.pdf', ['Attachment' => false]);
    }

    public function salesOrder(SalesOrder $order)
    {
        $order->load(['customer', 'items.product']);
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.document', ['type' => 'Sales Order', 'model' => $order]);
        return $pdf->stream('sales-order-' . $order->id . '.pdf', ['Attachment' => false]);
    }

    public function purchaseOrder(PurchaseOrder $order)
    {
        $order->load(['supplier', 'items.product']);
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.document', ['type' => 'Purchase Order', 'model' => $order]);
        return $pdf->stream('purchase-order-' . $order->id . '.pdf', ['Attachment' => false]);
    }

    public function quotation(Quotation $quotation)
    {
        $quotation->load(['customer', 'items.product', 'lead']);
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.document', ['type' => 'Quotation', 'model' => $quotation]);
        return $pdf->stream('quotation-' . $quotation->id . '.pdf', ['Attachment' => false]);
    }

    public function userManual()
    {
        $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.manual');
        return $pdf->stream('scm-erp-user-manual.pdf', ['Attachment' => false]);
    }
}

