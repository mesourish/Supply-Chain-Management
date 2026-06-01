# Project Brain: SCM ERP

## Overview
This file serves as the core memory and AI context for the Supply Chain Management (SCM) ERP system built with Laravel. It tracks architecture, decisions, and system modules as defined in `specification.txt`.

## Technical Stack
- **Backend:** Laravel 11, PHP 8.2, Livewire Volt (TALL Stack approach)
- **Database:** MySQL (MariaDB via XAMPP)
- **Roles & Permissions:** `spatie/laravel-permission`
- **State Management:** Custom Livewire state tracking and Eloquent model observers
- **Frontend:** AlpineJS, Tailwind CSS, Blade Components, ECharts for Dashboards
- **Visualizations:** ECharts (Sankey diagrams and analytical widgets) used extensively on Dashboards instead of ApexCharts.

## Architecture Guidelines
- **Senior Developer Standards:** Focus on memory efficiency, N+1 query prevention (eager loading), robust database indexing, and SOLID principles.
- **Data Retention:** Use `$table->softDeletes()` heavily for historical supply chain data tracking.
- **Dynamic Localization & Branding:** All dates, currencies, logos, company addresses, and timezone settings are fully dynamic via the `settings()` helper and stored in `system_constants`/Settings DB.
- **Dynamic VAT/GST:** Inclusive and Exclusive GST handling is enforced via system settings, automatically computing sub-totals, taxes, and grand totals across Purchase Orders, Sales Orders, and Invoices.
- **Constants Management:** All configurable elements (Expense Categories, Product Categories, Roles, HR Modules, default PO Terms and Remarks) are managed dynamically through `admin/constants` and settings panels.

## Core Modules (Implemented)
1. **RBAC (Admin):** Super Admin (bypass all), customizable roles with fine-grained granular permissions per module.
2. **Settings (Admin):** General Info, Localization (Timezone, Date Format, Currency), System & Procurement (Default GST, Map coordinates, Terms & Conditions, PO prefixes).
3. **Procurement:** RFQ, Purchase Orders (with GST breakups, Remarks, Terms & Conditions dynamically injected from Settings), Goods Receipt Note (GRN).
4. **Inventory & WMS:** Multi-warehouse, Bin mapping, stock transactions, Bin transfers, Inventory Analytics Dashboard with ECharts DFD Sankey charts.
5. **Order Management:** Quotations, Sales Orders, Shipments, dynamic pricing, Pick-Pack-Ship workflows.
6. **Finance:** Expenses, Accounts Payable (AP), Accounts Receivable (AR), Payment Certificates, Invoices with Corporate Letterhead rendering.
7. **Logistics / Fleet Management:** Vehicles, Drivers, real-time map plotting for active shipments with interconnected data.
8. **HRMS:** Human Resource Management System for attendance, leaves, payroll processing, and employee lifecycle management.
9. **Reverse Logistics:** Return Requests (RMA), Condition evaluation (Good/Damaged), and automatic Inventory Restocking workflows (`BinProductStock` handling).
10. **CRM & Projects:** Lead management, Pipeline tracking, Project milestones, and interconnected dependencies.

## Key Implementations & Rules
- **Seeding:** The `RealisticDataSeeder` handles end-to-end relational mapping for the entire ERP to ensure complex DFDs and maps reflect real-world data without UNIQUE constraint violations. Re-seeding should use `php artisan migrate:fresh --seed`.
- **Navigation Flow:** Sidebars use robust AlpineJS state management (`x-data="{ sidebarOpen: true, inventoryOpen: request()->is('inventory*'), ... }"`) to keep correct menus open.
- **Document Formatting:** Purchase Orders, Sales Orders, and Invoices dynamically render corporate letterheads, prefixes, dates (`Y-m-d H:i` formatting via settings), and statuses natively via Livewire `show.blade.php` pages.
- **Polymorphic Address lifecycle hooks:** `Customer` and `Supplier` models use static `booted()` listeners to automatically create or update morphMany `addresses` table entries when their flat address columns are saved, eliminating profile omissions and validation bottlenecks.
- **Double-Entry Ledger Observers:** Static observers in `PaymentLog`, `AccountReceivable`, and `Invoice` execute a self-healing cascade to recalculate balances and sync statuses (`paid`, `partial`, `unpaid`) dynamically across modules without N+1 query bottlenecks.
- **Tooling rules:** No placeholder charts! All visual charts use ECharts CDN.

## Current Status
- ERP modules are fundamentally complete, interconnected, and dynamic. 
- Fully deployed and functional with localized branding and robust GST financial engines.
- HRMS module and Logistics map integrations are live.
- CRM conversion flows (Leads -> Quotations -> Sales Orders) with automatic address/contact and quote item mappings are fully implemented and verified.
- Continuous polish and optimizations are applied iteratively across UI components to match "Senior Developer" grade quality.
- **Tests Validation**: Appended advanced feature integration suites in `ComprehensiveModulesTest.php` with all 44 tests and 197 assertions 100% green.
