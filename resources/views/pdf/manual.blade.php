<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SCM ERP - Simplified Software User Manual</title>
    <style>
        @page {
            margin: 60px 50px 60px 50px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #334155;
            line-height: 1.5;
        }
        h1, h2, h3 {
            color: #1e293b;
        }
        h1 {
            font-size: 20px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 5px;
            margin-top: 35px;
            color: #4f46e5;
            page-break-before: always;
        }
        h1.first-page {
            page-break-before: avoid;
            margin-top: 150px;
            text-align: center;
            border-bottom: none;
            font-size: 32px;
        }
        h2 {
            font-size: 14px;
            margin-top: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
        }
        p {
            margin-bottom: 10px;
            text-align: justify;
        }
        ul, ol {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        li {
            margin-bottom: 5px;
        }
        .cover-page {
            text-align: center;
            height: 100%;
        }
        .subtitle {
            font-size: 18px;
            color: #64748b;
            margin-top: 10px;
            font-style: italic;
        }
        .version {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 250px;
        }
        .footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            height: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .tip-box {
            background-color: #f8fafc;
            border-left: 4px solid #4f46e5;
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .tip-title {
            font-weight: bold;
            color: #4f46e5;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        
        /* Table Styles */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table.data-table th {
            background-color: #4F46E5;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: left;
        }
        table.data-table td {
            font-size: 9px;
            border: 1px solid #e2e8f0;
            padding: 8px;
            color: #334155;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        /* Flowchart Styles */
        .flow-box {
            background-color: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            font-weight: bold;
            color: #1e293b;
            font-size: 11px;
            text-align: center;
        }
        .flow-arrow {
            text-align: center;
            font-size: 18px;
            color: #4f46e5;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    <!-- Cover Page -->
    <div class="cover-page">
        <h1 class="first-page">SCM ERP SYSTEM</h1>
        <div class="subtitle">The Simplified Software User Manual</div>
        <p style="margin-top:50px; font-size:14px; color:#475569; text-align:center;">A Step-by-Step Practical Operator's Guide</p>
        
        <div class="version">
            Version 2.0 (June 2026)<br>
            Designed for Super Admins and Operational Staff
        </div>
    </div>
    
    <!-- Table of Contents -->
    <h1>Table of Contents</h1>
    <div style="font-size:14px; line-height:2; margin-top:30px;">
        <strong>1. System Overview &amp; Dashboard</strong> <span style="color:#cbd5e1;">...............................................................</span> Page 3<br>
        <strong>2. CRM Leads &amp; Quotations</strong> <span style="color:#cbd5e1;">........................................................................</span> Page 4<br>
        <strong>3. Sales &amp; Customer Management</strong> <span style="color:#cbd5e1;">..............................................................</span> Page 5<br>
        <strong>4. Inventory &amp; Warehouse Management (WMS)</strong> <span style="color:#cbd5e1;">..........................................</span> Page 6<br>
        <strong>5. Procurement (RFQs, POs, &amp; GRN)</strong> <span style="color:#cbd5e1;">........................................................</span> Page 7<br>
        <strong>6. Manufacturing (BOM &amp; MO)</strong> <span style="color:#cbd5e1;">..................................................................</span> Page 8<br>
        <strong>7. Quality Control &amp; Assurance (QC)</strong> <span style="color:#cbd5e1;">...........................................................</span> Page 9<br>
        <strong>8. Fleet, Shipments, &amp; Logistics</strong> <span style="color:#cbd5e1;">..................................................................</span> Page 10<br>
        <strong>9. Financial Accounting &amp; Chart of Accounts</strong> <span style="color:#cbd5e1;">................................................</span> Page 11<br>
        <strong>10. Enterprise AI &amp; Demand Forecasting</strong> <span style="color:#cbd5e1;">.......................................................</span> Page 12<br>
    </div>

    <!-- Section 1: Dashboard -->
    <h1>1. System Overview &amp; Dashboard</h1>
    <p>Welcome to SCM ERP! The Dashboard is your primary workspace window and control room. It consolidates metrics from all other business modules in real-time, allowing operators to monitor system health at a glance.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Finance KPIs<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">$150K Cash Balance</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Fleet Status<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">V-100 Delivery Van</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Stock Alerts<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Critical Reorders</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Log into the system to load the main dashboard.</li>
        <li>Scan the top numerical tiles to check overall performance.</li>
        <li>Check the warnings panel for critical tasks (e.g. low inventory items).</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Metric Category</th>
                <th>Real-Life Parameter</th>
                <th>Current Value</th>
                <th>Status Indicator</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Cash &amp; Liquidity</td>
                <td>Primary Bank Reserves</td>
                <td>$150,000.00</td>
                <td>Healthy</td>
            </tr>
            <tr>
                <td>Accounts Receivable</td>
                <td>Outstanding Customer Invoices</td>
                <td>$29,500.00</td>
                <td>Pending Payment</td>
            </tr>
            <tr>
                <td>Accounts Payable</td>
                <td>Outstanding Supplier Bills</td>
                <td>$3,000.00</td>
                <td>Due in 15 Days</td>
            </tr>
            <tr>
                <td>Low Stock Warning</td>
                <td>Raw Material RM-STL-42U</td>
                <td>2 Units</td>
                <td>Critical Alert (Safety: 5)</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 2: CRM -->
    <h1>2. CRM Leads &amp; Quotations</h1>
    <p>The Customer Relationship Management (CRM) module tracks prospective client conversations and converts pricing quotes to confirmed sales contracts. In our real-life scenario, TechVenture Solutions Inc. inquires about purchasing high-grade Enterprise Server Racks.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        CRM Lead<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">TechVenture Lead</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Draft Quote<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">QT-2026-001 ($25k)</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Confirmed SO<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">SO-2026-001 (Locked)</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Open 'CRM Leads' from the sidebar and click 'Add Lead'. Fill in client details for TechVenture Solutions Inc.</li>
        <li>Create a Quotation from the lead card, selecting the target product 'Enterprise Server Racks' (SKU: FG-SRV-42U).</li>
        <li>Set price, tax rate (GST 18%), and save. Once approved by the customer, click 'Convert to Sales Order'.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Lead Reference</th>
                <th>Prospect Customer</th>
                <th>Target SKU</th>
                <th>Qty Request</th>
                <th>Quoted Price</th>
                <th>Total Tax (18%)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>LD-2026-081</td>
                <td>TechVenture Solutions Inc.</td>
                <td>FG-SRV-42U</td>
                <td>10 Units</td>
                <td>$2,500.00</td>
                <td>$4,500.00</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 3: Sales -->
    <h1>3. Sales &amp; Customer Management</h1>
    <p>Oversee customer directory records, Sales Orders (SO), invoice billing, and customer product return requests (RMA). Converting the quote creates Sales Order SO-2026-001, which locks the inventory allocation.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Sales Order<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">SO-2026-001 Processing</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Generate Inv<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">INV-2026-001 Issued</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Receive Cash<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Debit Bank JV-1050</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Navigate to Sales -> Sales Orders. Check that SO-2026-001 is generated in draft.</li>
        <li>Verify TechVenture Solutions Inc.'s shipping address and update the order status to 'processing'.</li>
        <li>Click 'Generate Invoice' to issue invoice INV-2026-001. Mark as paid when payments arrive.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Invoice ID</th>
                <th>Client Name</th>
                <th>Total Billable</th>
                <th>Payment Status</th>
                <th>RMA Log</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>SO-2026-001</td>
                <td>INV-2026-001</td>
                <td>TechVenture Solutions</td>
                <td>$29,500.00</td>
                <td>Unpaid (Pending)</td>
                <td>RMA-2026-001 (Bezel)</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 4: Inventory -->
    <h1>4. Inventory &amp; Warehouse Management (WMS)</h1>
    <p>The Warehouse Management System (WMS) tracks real-time quantities of goods located inside physical bins, racks, zones, and warehouses. Items are organized in a strict logical tree to allow operators to find stock instantly.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        WMS Tree Layout<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Warehouse &rarr; Zone &rarr; Bin</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Stock Take Audit<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Audit Count Verification</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Reconcile Variance<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Post Discrepancies</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Open 'Inventory' -> 'Warehouses'. Review locations: Warehouse WH-01, Zone Z-RAW, Rack R-03, Bin A02-R2-S1.</li>
        <li>Use internal transfer to move components from receiving to assembly bins.</li>
        <li>Perform a physical Stock Take. If a discrepancy is found, submit counts to auto-adjust.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Warehouse ID</th>
                <th>Zone Name</th>
                <th>Rack ID</th>
                <th>Bin Address</th>
                <th>SKU Stored</th>
                <th>System Stock</th>
                <th>Audit Variance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>WH-01 (Central)</td>
                <td>Z-RAW (Raw Materials)</td>
                <td>R-03</td>
                <td>A02-R2-S1</td>
                <td>RM-STL-42U</td>
                <td>12 Units</td>
                <td>-2 (Adjusted)</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 5: Procurement -->
    <h1>5. Procurement (RFQs, POs, &amp; GRN)</h1>
    <p>Procurement manages supplier listings, bid tracking (RFQs), purchase orders (PO), and goods receiving (GRN). This initiates the flow by securing the raw steel frames required to assemble the server racks.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Supplier Bid Award<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">RFQ-2026-001 Approved</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Issue Order<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">PO-2026-001 ($3,000.00)</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Goods Receipt<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">GRN-2026-001 to Bins</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Create Supplier RFQ RFQ-2026-001. Send to Global Iron &amp; Steel Co.</li>
        <li>Accept their quote and generate PO-2026-001 for 10 units of RM-STL-42U.</li>
        <li>When delivery arrives, click 'Receive Goods' to create GRN-2026-001, moving stock to bin A02-R2-S1.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>RFQ Reference</th>
                <th>PO Reference</th>
                <th>Awarded Supplier</th>
                <th>SKU Ordered</th>
                <th>Qty Received</th>
                <th>GRN ID</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>RFQ-2026-001</td>
                <td>PO-2026-001</td>
                <td>Global Iron &amp; Steel Co.</td>
                <td>RM-STL-42U</td>
                <td>10 Units</td>
                <td>GRN-2026-001</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 6: Manufacturing -->
    <h1>6. Manufacturing (BOM &amp; MO)</h1>
    <p>Set up product recipes (Bills of Materials) and execute Manufacturing Orders (MO) to assemble raw components into finished items. Completed MOs auto-consume component items and add finished products to inventory.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Recipe Configuration<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">BOM-FG-42U Details</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Production Release<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">MO-2026-001 Runs</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Fulfill Finished Item<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Increase Rack Stocks</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Open Manufacturing -> Bills of Materials (BOM). Create recipe for finished product FG-SRV-42U.</li>
        <li>Go to Manufacturing Orders (MO). Click 'New Order', choose BOM and quantity 10, then confirm.</li>
        <li>Progress the MO status to 'Produce'. Upon completion, select destination bin A02-R3-S2.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>BOM Recipe ID</th>
                <th>Manufacturing MO</th>
                <th>Finished SKU</th>
                <th>Target Qty</th>
                <th>Raw Material Debits</th>
                <th>Finished Goods Credits</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>BOM-FG-42U</td>
                <td>MO-2026-001</td>
                <td>FG-SRV-42U</td>
                <td>10 Units</td>
                <td>10x RM-STL-42U</td>
                <td>10x FG-SRV-42U</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 7: Quality Control -->
    <h1>7. Quality Control &amp; Assurance (QC)</h1>
    <p>Prevents defective inventory from being shipped to customers or placed into active warehouse storage. All incoming items (GRN) and completed assembly runs (MO) trigger pending QC audits.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Production complete<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Automatic QC trigger</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Verify Standards<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Inspect QC-2026-001</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Audit Decision<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Release to CentralWH</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Go to the Quality Audits dashboard. Locate the pending check triggered by MO-2026-001.</li>
        <li>Click 'Audit Release'. Review inspection points (dimensions, rivets, grounding).</li>
        <li>Register the verdict as 'Passed'. This releases the 10 units to active inventory.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Audit Reference</th>
                <th>Origin Document</th>
                <th>Audited SKU</th>
                <th>Inspector Name</th>
                <th>QC Verdict</th>
                <th>Reconciled Date</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>QC-2026-001</td>
                <td>MO-2026-001</td>
                <td>FG-SRV-42U</td>
                <td>Alice Smith</td>
                <td>Passed (Released)</td>
                <td>2026-06-07</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 8: Fleet -->
    <h1>8. Fleet, Shipments, &amp; Logistics</h1>
    <p>Coordinates transport operations, vehicle profiles, fuel tracking, and driver dispatch routines. The logistics engine manages shipment schedules and posts ledger transactions upon completion.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Allocate Delivery<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">SH-2026-001 Scheduled</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Dispatch Vehicle<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Ford Transit Assigned</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Mark Delivered<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Trigger COGS ledgers</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Open Logistics -> Dispatch Board. Check pending sales shipment SH-2026-001.</li>
        <li>Drag and assign the shipment to Driver John Doe and Delivery Van V-100.</li>
        <li>Mark shipment as 'Delivered' upon client arrival to trigger COGS ledger entries.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Shipment ID</th>
                <th>Driver Name</th>
                <th>Vehicle ID</th>
                <th>Destination Route</th>
                <th>Delivery Status</th>
                <th>COGS Debit</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>SH-2026-001</td>
                <td>John Doe</td>
                <td>V-100 (Ford Transit)</td>
                <td>WH-01 to TechVenture HQ</td>
                <td>Delivered</td>
                <td>$6,500.00</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 9: Accounting -->
    <h1>9. Financial Accounting &amp; Chart of Accounts</h1>
    <p>Provides compliance-ready ledger reporting. SCM ERP automatically generates standard double-entry journal postings as business actions occur, ensuring financial integrity.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Invoice Post<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Debit Accounts Receivable</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Payment Receipt<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Credit Accounts Receivable</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        COGS Deduction<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">Shipment Completion Release</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Navigate to Finance -> General Ledger to view the double-entry transactions log.</li>
        <li>Open Chart of Accounts. Observe assets incremented and liabilities cleared.</li>
        <li>Use filters to review balance sheets and verify that debits equal credits.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Journal ID</th>
                <th>Posting Date</th>
                <th>Account Name</th>
                <th>Debit Amount</th>
                <th>Credit Amount</th>
                <th>Transaction Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>JV-2026-1049</td>
                <td>2026-06-07</td>
                <td>Accounts Receivable</td>
                <td>$29,500.00</td>
                <td>$0.00</td>
                <td>Invoice INV-2026-001 TechVenture</td>
            </tr>
            <tr>
                <td>JV-2026-1049</td>
                <td>2026-06-07</td>
                <td>Sales Revenue</td>
                <td>$0.00</td>
                <td>$25,000.00</td>
                <td>Invoice INV-2026-001 TechVenture</td>
            </tr>
            <tr>
                <td>JV-2026-1049</td>
                <td>2026-06-07</td>
                <td>GST Liabilities</td>
                <td>$0.00</td>
                <td>$4,500.00</td>
                <td>Invoice INV-2026-001 TechVenture</td>
            </tr>
            <tr>
                <td>JV-2026-1050</td>
                <td>2026-06-07</td>
                <td>Cash &amp; Bank</td>
                <td>$29,500.00</td>
                <td>$0.00</td>
                <td>Payment received from TechVenture</td>
            </tr>
            <tr>
                <td>JV-2026-1050</td>
                <td>2026-06-07</td>
                <td>Accounts Receivable</td>
                <td>$0.00</td>
                <td>$29,500.00</td>
                <td>Payment received from TechVenture</td>
            </tr>
            <tr>
                <td>JV-2026-1051</td>
                <td>2026-06-07</td>
                <td>Cost of Goods Sold</td>
                <td>$6,500.00</td>
                <td>$0.00</td>
                <td>COGS for shipment SH-2026-001</td>
            </tr>
            <tr>
                <td>JV-2026-1051</td>
                <td>2026-06-07</td>
                <td>Finished Goods Inventory</td>
                <td>$0.00</td>
                <td>$6,500.00</td>
                <td>Inventory deduction for SH-2026-001</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 10: AI Forecasting -->
    <h1>10. Enterprise AI &amp; Demand Forecasting</h1>
    <p>Optimize stock levels and prevent cash flow blocks with automated predictive demand algorithms. By calculating daily average usage, the forecasting module alerts staff and generates replenishment suggestions.</p>
    
    <div style="margin: 15px 0;">
        <span style="font-size: 11px; font-style: italic; color: #4f46e5; font-weight: bold;">Workflow Process Diagram:</span>
        <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
            <tr>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Velocity Analysis<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">1.2 units/day consumption</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Safety Adjustment<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">130% Multiplier Slider</span>
                    </div>
                </td>
                <td class="flow-arrow" style="width: 8%;">&#10142;</td>
                <td style="width: 28%;">
                    <div class="flow-box">
                        Purchase Drafts<br>
                        <span style="font-size: 9px; font-weight: normal; color: #64748b;">PO-2026-002 Triggered</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Step-by-Step Operator Guide:</h2>
    <ul>
        <li>Open AI Forecasting. Review consumption velocity calculations.</li>
        <li>Adjust safety stock multiplier (e.g. 130%) to simulate peak seasons.</li>
        <li>Click 'Apply Safety Settings' and then 'Bulk Auto-Generate POs' to create drafts.</li>
    </ul>

    <h2>Real-Life Transactional Example (TechVenture Solutions Inc.):</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Product SKU</th>
                <th>Avg Daily Velocity</th>
                <th>Lead Time (Days)</th>
                <th>Safety Multiplier</th>
                <th>Current Stock Level</th>
                <th>Replenish PO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>RM-STL-42U</td>
                <td>1.2 Units</td>
                <td>3 Days</td>
                <td>130% (Seasonality)</td>
                <td>2 Units (Alert)</td>
                <td>PO-2026-002 (Draft)</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        SCM ERP System Simplified Manual &copy; 2026. All rights reserved.
    </div>

</body>
</html>
