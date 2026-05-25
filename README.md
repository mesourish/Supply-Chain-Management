# SCM ERP (Supply Chain Management System)

A robust, full-featured Supply Chain Management (SCM) ERP built with **Laravel 11**, **Livewire Volt**, **Alpine.js**, and **Tailwind CSS**. This software provides an end-to-end operational backbone for businesses, seamlessly connecting Procurement, Inventory, Sales, Logistics, and Financial management.

---

## 🌟 Key Features

### 📦 Procurement & Suppliers
- **Supplier Directory:** Comprehensive management of vendor details and performance metrics.
- **Purchase Orders (POs):** Generate, approve, and track POs.
- **Goods Receipt Notes (GRN):** Log incoming deliveries against POs. Supports partial receipts.

### 🏭 Inventory & Warehouse Management
- **Multi-Warehouse & Rack Tracking:** Define warehouses, zones, racks, rows, and individual bins.
- **Stock Tracking:** Real-time inventory logs with `source` and `destination` bin traceability.
- **Stock Take & Adjustments:** Perform routine inventory audits and manual discrepancy adjustments.

### 💼 Sales & CRM
- **Customer Directory:** Track clients and their order histories.
- **Sales Orders (SOs):** Create and track outbound sales.
- **Order Fulfillment:** Pick, pack, and ship items directly from assigned inventory bins.
- **Returns (RMA):** Process and log customer returns directly into inventory.

### 🚚 Fleet & Logistics
- **Driver & Vehicle Management:** Log active vehicles and driver details.
- **Shipment Tracking:** Assign drivers to specific fulfillment orders and track delivery statuses.

### 💳 Finance & Accounting
- **Accounts Payable (AP):** Track balances owed to suppliers. Supports multiple/split payments and receipt attachments.
- **Accounts Receivable (AR):** Track balances owed by customers. Supports split payments and proof-of-payment attachments.
- **Purchase Expenses:** Track operational expenses (e.g., shipping, customs) optionally linked to POs.

---

## 🚀 Installation Guide

### Prerequisites
- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL / MariaDB

### Local Setup
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
   *Update your `.env` file with your database credentials.*

4. **Database Migration & Seeding**
   ```bash
   php artisan migrate --seed
   ```
   *This will run all migrations and populate the system with essential roles, permissions, and demo data.*

5. **Start the Application**
   ```bash
   php artisan serve
   ```
   Visit `http://localhost:8000` to log in. (Default credentials are provided by the seeder).

---

## 📖 User Guide

- **Dashboard:** The central hub for key metrics. You must have the `view dashboard` permission to access this.
- **Settings:** An admin-only area where you can dynamically adjust system preferences (Currency symbols, company details).
- **Workflows:** 
  - *Inbound:* Create a Purchase Order -> Approve it -> Log a GRN when items arrive to inject them into inventory.
  - *Outbound:* Create a Sales Order -> Fulfill it (picking items from stock) -> Dispatch via Logistics.

---

## 🤝 Contributing

We welcome community contributions to make this ERP even better! 

### Branch Protection (Important!)
To maintain code integrity, the `main` branch is strictly **protected**. 
**You cannot push code directly to `main`.**

### Contribution Workflow:
1. **Fork** the repository.
2. **Create a branch** for your feature or bug fix: `git checkout -b feature/my-new-feature`
3. **Commit** your changes: `git commit -m "Add new feature"`
4. **Push** to your fork: `git push origin feature/my-new-feature`
5. **Open a Pull Request (PR)** against the `main` branch of this repository.

*All PRs require review and approval before they can be merged.*

---
*Built with ❤️ using Laravel & Livewire.*
