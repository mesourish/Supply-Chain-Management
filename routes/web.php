<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\PdfExportController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // PDF Export Routes
    Route::get('/pdf/invoice/{invoice}', [PdfExportController::class, 'invoice'])->name('pdf.invoice');
    Route::get('/pdf/sales-order/{order}', [PdfExportController::class, 'salesOrder'])->name('pdf.salesOrder');
    Route::get('/pdf/purchase-order/{order}', [PdfExportController::class, 'purchaseOrder'])->name('pdf.purchaseOrder');

    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');

    Volt::route('intelligence/forecasting', 'intelligence.forecasting')->name('intelligence.forecasting');


    // Admin & Users
    Volt::route('admin/roles', 'admin.roles')
        ->name('admin.roles')
        ->middleware('can:manage roles');
        
    Volt::route('admin/users', 'admin.users')
        ->name('admin.users')
        ->middleware('can:manage users');

    // Inventory Module
    Volt::route('products', 'products.index')->name('products.index');
    Volt::route('warehouses', 'warehouses.index')->name('warehouses.index');
    Volt::route('warehouses/stock-take', 'warehouses.stock-take.index')->name('stock-take.index');
    Volt::route('warehouses/{id}', 'warehouses.show')->name('warehouses.show');
    Volt::route('inventory/log', 'inventory.log')->name('inventory.log');
    Volt::route('inventory/adjustments', 'inventory.adjustments')->name('inventory.adjustments');


    // CRM Module
    Volt::route('crm/leads', 'crm.leads')->name('crm.leads');

    // Procurement Module
    Volt::route('suppliers', 'suppliers.index')->name('suppliers.index');
    Volt::route('suppliers/{supplier}', 'suppliers.show')->name('suppliers.show');
    Volt::route('procurement/rfqs', 'procurement.rfqs')->name('procurement.rfqs');
    Volt::route('procurement/purchase-orders', 'procurement.purchase-orders.index')
        ->name('purchase-orders.index');
    Volt::route('procurement/grn', 'procurement.grn.index')
        ->name('grn.index');
    Volt::route('procurement/purchase-orders/{order}', 'procurement.purchase-orders.show')->name('purchase-orders.show');

    // Sales Module
    Volt::route('customers', 'customers.index')->name('customers.index');
    Volt::route('customers/{customer}', 'customers.show')->name('customers.show');
    Volt::route('sales/orders', 'sales.orders.index')->name('sales-orders.index');
    Volt::route('sales/orders/{order}', 'sales.orders.show')->name('sales-orders.show');
    Volt::route('sales/quotations', 'sales.quotations')->name('sales.quotations');
    Volt::route('sales/fulfillment', 'sales.fulfillment.index')->name('fulfillment.index');
    Volt::route('sales/returns', 'sales.returns.index')->name('returns.index');
    Volt::route('sales/returns/{id}', 'sales.returns.show')->name('returns.show');

    // Logistics
    Volt::route('logistics/dispatch', 'logistics.dispatch')
        ->name('dispatch.index');
    Volt::route('logistics/vehicles', 'logistics.vehicles.index')
        ->name('vehicles.index');
    Volt::route('logistics/drivers', 'logistics.drivers.index')
        ->name('drivers.index');
    Volt::route('logistics/shipments', 'logistics.shipments.index')
        ->name('shipments.index');

    // Finance Module
    Volt::route('finance/payables', 'finance.payables')
        ->name('finance.payables')
        ->middleware('can:view payables');
    Volt::route('finance/receivables', 'finance.receivables')
        ->name('finance.receivables')
        ->middleware('can:view receivables');
    Volt::route('finance/invoices', 'finance.invoices')
        ->name('finance.invoices')
        ->middleware('can:view receivables');
    Volt::route('finance/expenses', 'finance.expenses')
        ->name('finance.expenses')
        ->middleware('can:view expenses');
    Volt::route('finance/payment-certificates', 'finance.payment-certificates')
        ->name('finance.certificates')
        ->middleware('can:view receivables');

    // Comprehensive Reports Module
    Volt::route('/reports', 'reports.index')->name('reports.index');
    Volt::route('/faq', 'faq.index')->name('faq.index');

    // Admin / Settings Module
    Volt::route('admin/settings', 'admin.settings.index')
        ->name('admin.settings');
        
    Volt::route('admin/constants', 'admin.constants')
        ->name('admin.constants')
        ->middleware('can:view constants'); 

    Volt::route('admin/imports', 'admin.imports.index')
        ->name('admin.imports');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
Route::get('/debug-url', function () {
    return response()->json([
        'url' => request()->url(),
        'root' => request()->root(),
        'path' => request()->path(),
        'fullUrl' => request()->fullUrl(),
        'route' => route('debug.url.test')
    ]);
})->name('debug.url.test');
