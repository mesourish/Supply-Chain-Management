# Project Brain: SCM ERP

## Overview
This file serves as the core memory and AI context for the Supply Chain Management (SCM) ERP system built with Laravel. It tracks architecture, decisions, and system modules as defined in `specification.txt`.

## Technical Stack
- **Backend:** Laravel 11, PHP 8.2
- **Database:** MySQL (MariaDB via XAMPP)
- **Roles & Permissions:** `spatie/laravel-permission`
- **State Machines:** `spatie/laravel-model-states`
- **Queues/Jobs:** Redis + Laravel Horizon
- **Broadcasting:** Laravel Reverb or Pusher (TBD)
- **Frontend:** TBD (TALL stack or Inertia.js)

## Architecture Guidelines
- **Senior Developer Standards:** Focus on memory efficiency, N+1 query prevention (eager loading), robust database indexing, and SOLID principles.
- **Data Retention:** Use `$table->softDeletes()` heavily for historical supply chain data tracking.
- **State Management:** Strict enforcement of state transitions for POs and Orders using state machines.

## Core Modules (Blueprint)
1. **RBAC:** Super Admin, Procurement Manager, Warehouse Staff, Logistics Driver, Supplier, Customer.
2. **Procurement (Procure-to-Pay):** RFQ, PO generation (triggered by low stock), Goods Receipt Note (GRN).
3. **Inventory & WMS:** Multi-warehouse, Bin mapping, barcode scanning, stock movements.
4. **Order Management (Order-to-Cash):** Sales Orders, Pick-Pack-Ship workflows, Invoicing.
5. **Logistics:** Shipment tracking, Fleet assignment.
6. **Finance:** Accounts Payable (AP), Accounts Receivable (AR), Landed Costing.
7. **Reverse Logistics:** RMAs, Inspection, Credit/Replacement.

## Current Status
- Project initialized.
- PHP 8.2 compatibility established via `composer.json` updates.
- Basic models created by user (PurchaseOrder, Invoice, etc.).
- Awaiting frontend stack decision and Phase 1 implementation approval.
