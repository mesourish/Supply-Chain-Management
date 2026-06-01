# SCM ERP (Supply Chain Management System)

## ℹ️ About
A robust, enterprise-grade Supply Chain Management (SCM) ERP built with **Laravel 11**, **Livewire Volt**, **Alpine.js**, and **Tailwind CSS**. This open-source software provides an end-to-end operational backbone for B2B enterprises, seamlessly connecting Customer Relationship Management (CRM), Sales Quotations, Sales Orders, Procurement (RFQs), multi-zone Warehouse Bins, Fleet Logistics, and Financial Ledgers.

---

## ⚡ Active Data Pipeline Visualizer

Below is an interactive, CSS-animated data pipeline representing how operational data streams seamlessly through the SCM ERP ecosystem. **Hover over nodes** to inspect active transitions.

<div align="center">
  <img src="docs/pipeline.svg" alt="Active Data Pipeline" width="100%" style="max-width: 900px;">
</div>

---

## 🎨 SCM Ecosystem Visualizer

This system operates as a unified, data-driven supply chain where customer demand directly triggers physical inventory movements, supplier acquisitions, and financial logs. The diagram below illustrates how all entities interact chronologically across active modules.

```mermaid
graph TD
    %% Define Styles
    classDef crm fill:#e0f2fe,stroke:#0284c7,stroke-width:2px;
    classDef procurement fill:#fef3c7,stroke:#d97706,stroke-width:2px;
    classDef inventory fill:#dcfce7,stroke:#16a34a,stroke-width:2px;
    classDef sales fill:#fce7f3,stroke:#db2777,stroke-width:2px;
    classDef finance fill:#fee2e2,stroke:#dc2626,stroke-width:2px;
    classDef logistics fill:#f3e8ff,stroke:#7c3aed,stroke-width:2px;

    subgraph CRMSec ["1. CRM & Demand Trigger"]
        Lead[CrmLead]:::crm
        Activity[CrmActivity]:::crm
        Lead -->|Converts to| Cust[Customer]:::crm
        Lead -->|Generates Draft| Quote[Quotation]:::sales
        Lead -.->|Logs activity| Activity
    end

    subgraph SalesSec ["2. Commercial Sales (Outbound)"]
        QuoteItem[QuotationItem]:::sales
        Quote -->|Contains| QuoteItem
        Quote -->|Approved & Converts to| SO[SalesOrder]:::sales
        SO -->|Linked to| Cust
    end

    subgraph ProcurementSec ["3. Procurement & Sourcing (Inbound)"]
        Rfq[Rfq]:::procurement
        PO[PurchaseOrder]:::procurement
        POItem[PurchaseOrderItem]:::procurement
        GRN[GoodsReceiptNote]:::procurement
        PO -->|Contains| POItem
        Rfq -->|Collect Bids & Wins| PO
    end

    subgraph InventorySec ["4. Inventory & WMS (Warehouse Bins)"]
        WH[Warehouse]:::inventory
        Bin[WarehouseBin]:::inventory
        Stock[BinProductStock]:::inventory
        Tx[InventoryTransaction]:::inventory
        WH -->|Contains| Bin
        Bin -->|Stores Quantity| Stock
        Tx -->|Logs Stock Move| Bin
        GRN -->|Injects Stock| Bin
        SO -->|Reserves & Deducts| Bin
    end

    subgraph LogisticsSec ["5. Fleet & Logistics Delivery"]
        Shipment[Shipment]:::logistics
        Driver[Driver]:::logistics
        Vehicle[Vehicle]:::logistics
        Shipment -->|Assigned Driver| Driver
        Shipment -->|Assigned Asset| Vehicle
        SO -->|Fulfill & Ship| Shipment
    end

    subgraph FinanceSec ["6. Accounting & General Ledger"]
        AP[AccountPayable]:::finance
        AR[AccountReceivable]:::finance
        PayLog[PaymentLog]:::finance
        Expense[Expense]:::finance
        GRN -->|Generates Bill| AP
        SO -->|Generates Invoice| AR
        AP -->|Records Payment| PayLog
        AR -->|Records Payment| PayLog
        PayLog -->|Compiles| Cert[Payment Certificate]:::finance
    end

    %% Key Inter-Module Links
    Stock -->|Below Reorder Level| Rfq
    SO -.->|Triggers Inbound/Outbound Trace| Tx
```

---

## 📊 SCM Pipeline Data Flow Diagram (DFD)

This Data Flow Diagram (DFD) maps the movement of data between external entities (users/clients), operational processes, database stores, and physical ledger records, showing how information flows from initial demand triggers through fulfillment and audit logs.

```mermaid
graph TD
    %% Define Styles
    classDef entity fill:#fff,stroke:#333,stroke-width:2px,stroke-dasharray: 5 5;
    classDef process fill:#f9fafb,stroke:#4b5563,stroke-width:2px;
    classDef datastore fill:#eef2ff,stroke:#6366f1,stroke-width:2px;

    %% External Entities (Dotted Boxes)
    Customer(("👤 B2B Customer")):::entity
    Supplier(("🏢 Supplier Vendor")):::entity
    Admin(("🧑‍💼 Warehouse Admin")):::entity
    Auditor(("🧑‍💻 Finance Auditor")):::entity

    %% Processes (Rounded Corners)
    P1["P1: Lead Pipeline & Conversions"]:::process
    P2["P2: Estimate Quotations & Sales Orders"]:::process
    P4["P4: Outbound Order Fulfillment"]:::process
    P5["P5: Multi-Supplier RFQ Bids & POs"]:::process
    P6["P6: Inbound Goods Receipt Note"]:::process
    P7["P7: Manual Stock Adjustments"]:::process
    P8["P8: General Ledger Ledger & Receipts"]:::process

    %% Data Stores (Double Bar / Open boxes)
    D1[("D1: CRM Opportunities &lt;crm_leads&gt;")]:::datastore
    D2[("D2: Warehouses & Bins Stocks &lt;bin_product_stocks&gt;")]:::datastore
    D4[("D4: Accounts Receivable Ledger &lt;account_receivables&gt;")]:::datastore
    D5[("D5: Accounts Payable Ledger &lt;account_payables&gt;")]:::datastore
    D6[("D6: Immutable Ledger Logs &lt;inventory_transactions&gt;")]:::datastore

    %% Data Flows
    Customer -->|Sales Inquiry| P1
    P1 -->|Log Opportunity| D1
    D1 -->|Won Lead| P2
    P2 -->|Create Profile| Customer
    Customer -->|Approve Quote| P2
    P2 -->|Reserve Bin Stock| D2
    D2 -->|Generate Pick List| P4
    P4 -->|Scan Barcode / Ship Cargo| Customer
    P4 -->|Permanent stock deduction| D2
    P4 -->|Record Inbound Invoice| D4

    %% Procurement side
    D2 -->|Below reorder levels| P5
    P5 -->|Dispatches RFQs| Supplier
    Supplier -->|Submit Bid Price| P5
    P5 -->|Raise PO| Supplier
    Supplier -->|Deliver Freight| P6
    P6 -->|GRN stock injection| D2
    P6 -->|FIFO Inbound Transaction Log| D6
    P6 -->|Record Bill| D5

    %% Adjustments
    Admin -->|Add / Remove / Transfer Stock| P7
    P7 -->|Recalculate quantities| D2
    P7 -->|Append manual log event| D6

    %% Finance
    Customer -->|Settle invoices / Pay balance| P8
    P8 -->|Update client ledger| D4
    P8 -->|Compile Cert matching selected payments| Customer
    Auditor -->|Inspect ledger transparency| D6
```

---

## 🔄 Core Workflows & Detailed Data Flows

### 1. Lead-to-Order Conversion Flow
This workflow demonstrates how customer interest captured in the CRM transitions seamlessly into signed contracts, auto-generating a standard B2B Sales Order alongside assigned warehouse stock reservations.

```mermaid
sequenceDiagram
    autonumber
    actor Staff as Sales / Operations Team
    actor Customer as B2B Client
    participant CRM as CRM Pipeline (leads.blade.php)
    participant Sales as Sales Quotations (quotations.blade.php)
    participant WMS as Inventory & Bins (WMS)
    participant SO as Sales Order (SalesOrders)

    CRM->>CRM: Log & Track Opportunity (CrmLead)
    CRM->>Staff: Hot Lead transitions to 'Proposal' status
    Staff->>CRM: Trigger 'Convert Lead to Customer'
    CRM->>Sales: Create Customer Record & Spawn Draft Quotation
    Staff->>Sales: Configure Itemized Estimates, Margin Check & Taxes
    Sales->>Customer: Present Final Quotation for Review
    Customer->>Staff: Quote Accepted & Signed off
    Staff->>Sales: Mark Quotation as 'Approved'
    rect rgba(0, 150, 160, 0.1)
        Note over Sales, SO: Automatic Multi-Entity Engine
        Sales->>Sales: Auto-Generate Inbound B2B Sales Order (SalesOrder)
        Sales->>SO: Map Addresses & Polymorphic Primary Contacts
    end
    SO->>WMS: Check available warehouse stock (BinProductStock)
    SO->>WMS: Reserve Bin-Level Product Stocks for Outbound Dispatch
    Note over WMS, SO: Locks stock specifically for fulfillment, ensuring transparent allocations!
```

* **CRM Lead Kanban Component:** [leads.blade.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/resources/views/livewire/crm/leads.blade.php) - Manages leads, records client communications, and handles one-click conversions.
* **Customer Profile Console:** [show.blade.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/resources/views/livewire/customers/show.blade.php) - Displays full order history, Outstanding Accounts Receivables (AR) management, and compiled payment certificates.
* **Quotation Management Workspace:** [quotations.blade.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/resources/views/livewire/sales/quotations.blade.php) - Itemized quote calculator that automatically converts won estimates into active sales orders.

---

### 2. Procure-to-Pay Flow (Inbound Stock Lifecycle)
This workflow handles critical inbound sourcing: tracking inventory depletion, dispatching multi-vendor RFQs, recording and evaluating bids, issuing formal POs, generating GRNs upon cargo arrival, and logging corresponding Accounts Payable bills.

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Procurement Manager
    actor Vendor as External Supplier
    participant WMS as Warehouse (WMS)
    participant Proc as RFQ & Sourcing (rfqs.blade.php)
    participant PO as Purchase Order (POs)
    participant GRN as Goods Receipt (GRN)
    participant Fin as Accounts Payable (AP)

    WMS->>WMS: Daily Stock Audit: BinProductStock < ReorderPoint
    WMS->>Proc: Raise Automatic Reorder Procurement Alert
    Admin->>Proc: Initiate RFQ for multiple Suppliers
    Proc->>Vendor: Dispatch RFQs electronically
    Vendor->>Proc: Log Supplier Bids (with bids pricing & delivery windows)
    Admin->>Proc: Review & Final Approve the Winning Bid
    Proc->>PO: Convert Winner Bid to Purchase Order (Draft -> Approved)
    PO->>Vendor: Send Approved PO PDF
    Vendor->>WMS: Deliver physical goods to Loading Dock
    WMS->>GRN: Log incoming delivery, inspect quantities & quality
    GRN->>WMS: Inbound Warehouse Bin Injections & Transaction Logs (FIFO)
    GRN->>Fin: Create Bill (AccountPayable)
    Admin->>Fin: Process Payment (Split or Full) & Upload Receipt File
    Fin->>Fin: Update Vendor balance ledger & general ledger
```

* **Supplier RFQ Compiler:** [rfqs.blade.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/resources/views/livewire/procurement/rfqs.blade.php) - Requests bids, logs vendor quotes, and translates approved proposals into standard Purchase Orders.
* **Warehouse Stock Models:** [WarehouseBin.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/app/Models/WarehouseBin.php) & [BinProductStock.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/app/Models/BinProductStock.php) - Tracks real-time quantities across multi-dimensional warehouse coordinate systems.

---

### 3. Order-to-Cash Flow (Outbound Fulfillment & Certificates)
This outbound path covers customer order receipt, inventory matching, Pick list processing in the WMS, driver delivery routing, invoicing, split payment collection, and secure corporate Payment Certificate generation.

```mermaid
sequenceDiagram
    autonumber
    actor Cust as B2B Customer
    actor Admin as Warehouse/Logistics Staff
    participant SO as Sales Order (SO)
    participant WMS as Inventory & Bins (WMS)
    participant Log as Fleet/Logistics (Shipment)
    participant Fin as Accounts Receivable (AR)

    Cust->>SO: Place B2B Sales Order or Approve Quote
    SO->>WMS: Lock & Reserve stock at individual Warehouse Bins
    WMS->>Admin: Generate Pick List (optimized path picker)
    Admin->>WMS: Pick & Pack from Bin coordinates
    WMS->>WMS: Record Stock Deductions & Inventory Transaction logs
    WMS->>Log: Initialize Delivery Manifest & Logistics Shipment
    Log->>Log: Assign Driver & Vehicle to optimal shipment routes
    Log->>Cust: Transit status update -> Package Delivered
    Log->>Fin: Trigger invoice billing (Invoice & AccountReceivable)
    Cust->>Fin: Pay balance (Splits supported) & upload proof of payment
    Fin->>Fin: Reconcile payments & adjust Customer AR Ledger balances
    Fin->>Cust: Generate Secure Payment Certificate PDF matching selected data
```

* **Receivables Editor & Payments Logs:** [show.blade.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/resources/views/livewire/customers/show.blade.php#L150) - Located inside the Customer Profile view. Allows administrators to modify outstanding receivables and record incoming payments directly.
* **Payment Certificate Generator:** [show.blade.php](file:///Applications/XAMPP/xamppfiles/htdocs/scm-erp/resources/views/livewire/customers/show.blade.php#L220) - Allows compiling selected customer payments into a printable/exportable corporate PDF document to verify client transactions.

---

## ⚖️ Stock Adjustments vs. Inventory Ledger (SOX & Audit Compliance)

In enterprise-level ERP architectures, a strict separation is maintained between operational calculations (physical inventory alterations) and historical reporting (the ledger trace):

| Dimension | 🏭 Stock Adjustments Console | 📘 Inventory Audit Ledger |
| :--- | :--- | :--- |
| **Purpose** | **Operational Actions:** Administrative panel where staff physically logs additions, removals, or transfers. | **Compliance Logs:** An immutable historical transaction log mapping all SCM ledger adjustments chronologically. |
| **Mutability** | **Active Calculations:** Recomputes actual quantities in the WMS (`bin_product_stocks`). | **Immutable:** Static, read-only transaction ledger entries that cannot be edited or manually added. |
| **Origin Triggers** | Triggered manually by admins for inventory corrections or stock movement transfers. | Spawned automatically by system triggers (GRNs, Shipments, RMAs, or Manual Adjustments). |
| **Capabilities** | Supports manual additions, manual removals, and complex **Bin-to-Bin stock transfers**. | Supports advanced filtering, searching, and product SKU tracking. |

### ⇅ The Bin-to-Bin Stock Transfer Engine
A custom, high-fidelity stock transfer module has been integrated into the **Manual Stock Adjustments** dashboard:
* **Current Balances Verification:** When an operator transfers stock, the engine validates that the source bin has sufficient physical inventory.
* **Double-Entry Balance Updates:** In a single, transaction-guaranteed block, the system automatically subtracts the selected quantity from the source bin coordinate and adds it to the target bin coordinate (creating a new stock index if the product was not previously present).
* **Unified Audit Logging:** Appends a single comprehensive transaction trace to the **Inventory Audit Ledger** detailing both the `from_bin_id` and the `to_bin_id` alongside user timestamps, ensuring absolute transparency.

---

## 🤖 Enterprise AI Modules

The SCM ERP incorporates advanced Artificial Intelligence capabilities designed to optimize supply chain operations and minimize human error:

- **AI Demand Forecasting (Active):** Utilizes historical sales data and seasonal trend analysis to predict future inventory demand, automatically suggesting optimal restock quantities to prevent stockouts or overstocking.
- **AI Supply Chain Co-Pilot (Upcoming):** An intelligent conversational assistant capable of answering complex natural language queries about supplier reliability, low-stock risks, and operational bottlenecks directly from the ERP database.
- **Smart Document Extraction (Upcoming):** AI OCR models intended to automatically parse PDF invoices and supplier quotations, instantly extracting line items, prices, and totals to eliminate manual data entry.
- **Supplier Risk Scoring (Upcoming):** A machine learning model that continuously evaluates supplier trust scores based on delivery delay patterns and defect rates to warn procurement officers before issuing massive Purchase Orders.

---

## 🌟 Key Features Summary

### 📦 Procurement & Suppliers
- **Supplier Directory:** Comprehensive management of vendor details and performance metrics.
- **Request for Quotation (RFQ):** Dispatch items to multiple suppliers and record vendor pricing side-by-side.
- **Purchase Orders (POs):** Generate, approve, and track POs.
- **Goods Receipt Notes (GRN):** Log incoming deliveries against POs with partial receipt support.

### 🏭 Inventory & Warehouse Management (WMS)
- **Multi-Warehouse & Rack Tracking:** Define warehouses, zones, racks, rows, and individual bins.
- **Stock Tracking:** Real-time inventory logs with `source` and `destination` bin traceability.
- **Stock Take & Adjustments:** Perform routine inventory audits and manual discrepancy adjustments.
- **Bin-to-Bin Stock Transfer Engine:** Transaction-guaranteed manual stock transfers with double-entry balance updates and comprehensive audit ledger logs.

### 💼 Sales & CRM
- **CRM Kanban Board:** Beautiful lead capture columns ("New", "Contacted", "Proposal", "Negotiation", "Won", "Lost") to track opportunities.
- **CRM Details Overlay:** High-fidelity opportunity detail modal with chronological interaction logs, A4 lead sheet printing, and direct SCM conversions.
- **Quotation Kanban Board:** Interactive, drag-and-drop quotation pipeline (`Draft`, `Sent`, `Accepted`, `Rejected`) with live deal volume trackers.
- **CRM Opportunity Converter:** One-click conversion from CRM Lead to draft Quotations with automatic `QuotationItem` line items, stage changes to `proposal` (60% probability), and CRM activity logging.
- **SCM Cascade Engine:** Automatic conversion of `Accepted` quotes into standard B2B Sales Orders, mapping polymorphic contacts and billing/shipping address IDs, updating linked leads to `won` (100% probability), and initiating stock reservations.
- **A4 Corporate Letterhead Isolation**: Native print isolator overlays and SHA-256 ERP verification hashes for invoices, quotations, and sales order summaries.
- **Lead Auto-Conversion:** Instantly convert won leads into Customer Profiles and draft Quotations.
- **Quotation Engine:** Dynamic tax, discount, and landed cost estimations.
- **Sales Orders (SOs):** Pick, pack, and ship items directly from assigned inventory bins.
- **Returns (RMA):** Process and log customer returns directly into inventory.

### 🚚 Fleet & Logistics
- **Driver & Vehicle Management:** Log active vehicles, drivers, and asset schedules.
- **Shipment Tracking:** Assign drivers to specific fulfillment orders and track delivery statuses.

### 💳 Finance & Accounting
- **Interactive Invoice CRUD:** Full invoice editing, deleting, dynamic detail sheets, and custom PDF generator.
- **Accounts Payable (AP):** Track supplier bills, split payments, and upload receipts.
- **Accounts Receivable (AR):** Manage customer invoices, split payments, and record transactions.
- **Bidirectional Payment Sync**: Accounts Receivable dynamically updates linked Invoices upon payment logs. Invoices propagate manual toggles back to receivables, and automatically create a `PaymentLog` entry for any outstanding balance when marked `paid`.
- **Self-Healing Payment Observers:** Static `PaymentLog` lifecycle hooks automatically recalculate and update parent receivable/payable balances and statuses (`paid`, `partial`, `unpaid`) upon saves or deletions, preventing stale data.
- **Outstanding Progress Bar:** Dynamic, real-time receivables status meter in the primary SCM dashboard.
- **Payment Certificate Compiler:** Generates custom-itemized corporate receipts for selected payments in PDF format.

### 🛡️ System Administration & Local Hosting
- **FixSubfolderIntendedUrl Middleware:** Solves XAMPP session-expiration redirect bypass bug under subdirectory installations (e.g. `/scm-erp/`).
- **Dynamic Filters:** Real-time timezone middleware integration and dashboard financial reporting interval parameters.
- **Root Redirection:** Automatic guest fallback from `/` to named route `'login'` with obsolete file clean-ups.

---

## 🚀 Installation Guide

### Option 1: Docker (Laravel Sail) - *Recommended*
If you have Docker Desktop installed, you can spin up the entire application without local environment configuration:

1. **Clone the Repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/scm-erp.git
   cd scm-erp
   ```
2. **Install Composer Dependencies** (Using a temporary Docker container)
   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php82-composer:latest \
       composer install --ignore-platform-reqs
   ```
3. **Setup Environment & Start Sail**
   ```bash
   cp .env.example .env
   ./vendor/bin/sail up -d
   ```
4. **Generate Key, Migrate & Seed**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate --seed
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run build
   ```
   Visit `http://localhost` to log in!

### Option 2: Local Setup (Valet, XAMPP, etc.)
1. **Clone the Repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/scm-erp.git
   cd scm-erp
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Update your `.env` file with your local database credentials.*

4. **Database Migration & Seeding**
   ```bash
   php artisan migrate --seed
   ```
   *This migrates the schema and seeds all admin permissions, module roles, and standard sample datasets.*

5. **Start the Application**
   ```bash
   php artisan serve
   ```
   Visit `http://localhost:8000` to log in.

---

## 🤝 Contributing

To maintain code integrity, the `main` branch is strictly **protected**. **You cannot push code directly to `main`.**

### Contribution Workflow:
1. **Fork** the repository.
2. **Create a branch** for your feature: `git checkout -b feature/my-new-feature`
3. **Commit** your changes: `git commit -m "Add new feature"`
4. **Push** to your fork: `git push origin feature/my-new-feature`
5. **Open a Pull Request (PR)** against the `main` branch.

*All PRs require automated test passes and code review approval before merging.*

---
*Built with ❤️ using Laravel & Livewire.*

---

## 📄 License & Open Source Agreement

This project is licensed under the **MIT License**. 

By using, distributing, or contributing to this software, you agree to the terms and conditions outlined in the [LICENSE](LICENSE) file. This software is provided "as is", without warranty of any kind, express or implied.
