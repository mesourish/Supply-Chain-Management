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
3. **Procurement:** RFQ, Purchase Orders (with GST breakups, Remarks, Terms & Conditions dynamically injected from Settings), Goods Receipt Note (GRN) with linked manufacturing demand checks.
4. **Inventory & WMS:** Multi-warehouse, Bin mapping, stock transactions, Bin transfers, Inventory Analytics Dashboard with ECharts DFD Sankey charts.
5. **Order Management:** Quotations, Sales Orders, Shipments, dynamic pricing, Pick-Pack-Ship workflows.
6. **Finance:** Expenses, Accounts Payable (AP), Accounts Receivable (AR), Payment Certificates, Invoices with Corporate Letterhead rendering.
7. **Logistics / Fleet Management:** Vehicles, Drivers, real-time map plotting for active shipments with interconnected data.
8. **HRMS:** Human Resource Management System for attendance, leaves, payroll processing, and employee lifecycle management.
9. **Reverse Logistics:** Return Requests (RMA), Condition evaluation (Good/Damaged), and automatic Inventory Restocking workflows (`BinProductStock` handling).
10. **CRM & Projects:** Lead management, Pipeline tracking, Project milestones, and interconnected dependencies.
11. **Manufacturing (BOM & MO):** Bill of Materials recipe configurations, Manufacturing Orders pipeline stages (`Draft`, `Confirmed`, `In Progress`, `Quality Check`, `Completed`), and automatic component inventory deductions/yield increments on order completion.
12. **Quality Control (QC):** Inspection holds triggered automatically on cargo receipt and manufacturing production, inspector feedback consoles, and clearance releases.

## Key Implementations & Rules
- **Seeding:** The `RealLifeDataSeeder` handles end-to-end relational mapping for the entire ERP using cohesive real-world narrative data (TechVenture Solutions Inc. acquiring Enterprise Server Racks) matching the user manuals. All old, fragmented seeder files have been removed from the repository. Re-seeding uses `php artisan db:seed` or `php artisan migrate:fresh --seed`.
- **Navigation Flow:** Sidebars use robust AlpineJS state management (`x-data="{ sidebarOpen: true, inventoryOpen: request()->is('inventory*'), ... }"`) to keep correct menus open.
- **Document Formatting:** Purchase Orders, Sales Orders, and Invoices dynamically render corporate letterheads, prefixes, dates (`Y-m-d H:i` formatting via settings), and statuses natively via Livewire `show.blade.php` pages.
- **Polymorphic Address lifecycle hooks:** `Customer` and `Supplier` models use static `booted()` listeners to automatically create or update morphMany `addresses` table entries when their flat address columns are saved, eliminating profile omissions and validation bottlenecks.
- **Double-Entry Ledger Observers:** Static observers in `PaymentLog`, `AccountReceivable`, and `Invoice` execute a self-healing cascade to recalculate balances and sync statuses (`paid`, `partial`, `unpaid`) dynamically across modules. Double-entry accounting registers detailed debit and credit lines across general ledger accounts (Assets, Liabilities, Receivables, Payables, Revenue) automatically during operational processes.
- **Tooling rules:** No placeholder charts! All visual charts use ECharts CDN.

## Current Status
- ERP modules are fundamentally complete, interconnected, and dynamic. 
- Fully deployed and functional with localized branding and robust GST financial engines.
- HRMS module and Logistics map integrations are live.
- CRM conversion flows (Leads -> Quotations -> Sales Orders) with automatic address/contact and quote item mappings are fully implemented and verified.
- Manufacturing recipe building and shop-floor pipelines with inventory shortage checks are fully integrated with the sidebar.
- Quality Control holds and General Ledger postings are automatically wired to procurement/manufacturing order milestones.
- Standard User Manual files (`user_manual.pdf` & `user_manual.docx`) are compiled and downloadable from the interactive FAQ screen (`/faq`).
- **Tests Validation**: Expanded integration test suite to cover all upgrades. All 84 test assertions passed 100% green.
