# -*- coding: utf-8 -*-
import docx
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
import sys
import os

def add_table(doc, headers, data):
    doc.add_paragraph() # Spacing
    table = doc.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.style = 'Table Grid'
    
    # Style the headers
    hdr_cells = table.rows[0].cells
    for i, heading in enumerate(headers):
        hdr_cells[i].text = str(heading)
        for p in hdr_cells[i].paragraphs:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            for run in p.runs:
                run.font.bold = True
                run.font.size = Pt(9.5)
                run.font.color.rgb = RGBColor(0x1E, 0x29, 0x3B)
                
    # Populate data rows
    for row_data in data:
        row_cells = table.add_row().cells
        for i, val in enumerate(row_data):
            row_cells[i].text = str(val)
            for p in row_cells[i].paragraphs:
                p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                for run in p.runs:
                    run.font.size = Pt(9)
                    run.font.color.rgb = RGBColor(0x33, 0x41, 0x55)
    doc.add_paragraph() # Spacing

def add_diagram(doc, text):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.25)
    run = p.add_run(text)
    run.font.name = 'Consolas' # Monospace font for ASCII art
    run.font.size = Pt(8.5)
    run.font.color.rgb = RGBColor(0x4F, 0x46, 0xE5) # Indigo
    doc.add_paragraph() # Spacing

def create_manual(output_path):
    doc = docx.Document()
    
    # Page setup
    for section in doc.sections:
        section.top_margin = Inches(1)
        section.bottom_margin = Inches(1)
        section.left_margin = Inches(1)
        section.right_margin = Inches(1)

    # Styles Setup
    style_normal = doc.styles['Normal']
    style_normal.font.name = 'Arial'
    style_normal.font.size = Pt(11)
    style_normal.font.color.rgb = RGBColor(0x33, 0x41, 0x55) # Slate 700

    # Title Page
    title_p = doc.add_paragraph()
    title_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    title_run = title_p.add_run("\n\n\n\nSCM ERP SYSTEM\n")
    title_run.font.size = Pt(28)
    title_run.font.bold = True
    title_run.font.color.rgb = RGBColor(0x4F, 0x46, 0xE5) # Indigo 600
    
    subtitle_run = title_p.add_run("The Simplified Software User Manual\n")
    subtitle_run.font.size = Pt(16)
    subtitle_run.font.italic = True
    subtitle_run.font.color.rgb = RGBColor(0x64, 0x74, 0x8B) # Slate 500
    
    version_run = title_p.add_run("\nVersion 2.0 (June 2026)\nBuilt for Smooth Operations")
    version_run.font.size = Pt(10)
    
    doc.add_page_break()

    # Table of Contents placeholder (textual)
    h_toc = doc.add_heading(level=1)
    h_toc_run = h_toc.add_run("Table of Contents")
    h_toc_run.font.bold = True
    h_toc_run.font.color.rgb = RGBColor(0x1E, 0x29, 0x3B)
    
    toc_p = doc.add_paragraph()
    toc_p.add_run("1. System Overview & Dashboard............................................................... Page 3\n"
                 "2. CRM Leads & Quotations........................................................................ Page 4\n"
                 "3. Sales & Customer Management.............................................................. Page 5\n"
                 "4. Inventory & Warehouse Management (WMS).......................................... Page 6\n"
                 "5. Procurement (RFQs, POs, & GRN)........................................................ Page 7\n"
                 "6. Manufacturing (BOM & MO).................................................................. Page 8\n"
                 "7. Quality Control & Assurance (QC)........................................................... Page 9\n"
                 "8. Fleet, Shipments, & Logistics.................................................................. Page 10\n"
                 "9. Financial Accounting & Chart of Accounts................................................ Page 11\n"
                 "10. Enterprise AI & Demand Forecasting....................................................... Page 12\n")
    
    doc.add_page_break()

    # Helper function for adding styled sections
    def add_section(title, text, diagram_txt, step_list, headers, rows):
        h = doc.add_heading(level=1)
        hrun = h.add_run(title)
        hrun.font.bold = True
        hrun.font.color.rgb = RGBColor(0x4F, 0x46, 0xE5) # Indigo 600
        
        # Add explanation
        doc.add_paragraph(text)
        
        # Add flow diagram
        if diagram_txt:
            doc.add_paragraph().add_run("Workflow Process Diagram:").font.italic = True
            add_diagram(doc, diagram_txt)
            
        # Add steps
        sub_h = doc.add_heading(level=2)
        sub_run = sub_h.add_run("Step-by-Step Operator Guide")
        sub_run.font.bold = True
        sub_run.font.color.rgb = RGBColor(0x1E, 0x29, 0x3B)
        
        for step in step_list:
            p = doc.add_paragraph(style='List Bullet')
            p.add_run(step)
            
        # Add real life data table
        if headers and rows:
            sub_h2 = doc.add_heading(level=2)
            sub_run2 = sub_h2.add_run("Real-Life Transactional Example (TechVenture Solutions Inc.)")
            sub_run2.font.bold = True
            sub_run2.font.color.rgb = RGBColor(0x1E, 0x29, 0x3B)
            add_table(doc, headers, rows)
            
        doc.add_page_break()

    # Section 1: Dashboard
    add_section(
        "1. System Overview & Dashboard",
        "Welcome to SCM ERP! The Dashboard is your primary workspace window and control room. It consolidates metrics from all other business modules in real-time, allowing operators to monitor system health at a glance.",
        "┌────────────────────────────────────────────────────────┐\n"
        "│                   ERP DASHBOARD COCKPIT                │\n"
        "│                                                        │\n"
        "│  ┌────────────────┐ ┌────────────────┐ ┌────────────┐  │\n"
        "│  │  Finance KPIs  │ │  Fleet Status  │ │ Stock Alert│  │\n"
        "│  │  $150K Cash    │ │  V-100 Active  │ │  Critical  │  │\n"
        "│  └───────┬────────┘ └───────┬────────┘ └─────┬──────┘  │\n"
        "│          │                  │                │         │\n"
        "│          └──────────────────┼────────────────┘         │\n"
        "│                             ▼                          │\n"
        "│               Operations Decision / Procurement        │\n"
        "└────────────────────────────────────────────────────────┘",
        [
            "Log into the system to load the main dashboard.",
            "Scan the top numerical tiles to check overall performance.",
            "Check the warnings panel for critical tasks (e.g. low inventory items)."
        ],
        ['Metric Category', 'Real-Life Parameter', 'Current Value', 'Status Indicator'],
        [
            ['Cash & Liquidity', 'Primary Bank Reserves', '$150,000.00', 'Healthy'],
            ['Accounts Receivable', 'Outstanding Customer Invoices', '$29,500.00', 'Pending Payment'],
            ['Accounts Payable', 'Outstanding Supplier Bills', '$3,000.00', 'Due in 15 Days'],
            ['Low Stock Warning', 'Raw Material RM-STL-42U', '2 Units', 'Critical Alert (Safety: 5)']
        ]
    )

    # Section 2: CRM & Quotes
    add_section(
        "2. CRM Leads & Quotations",
        "The Customer Relationship Management (CRM) module tracks prospective client conversations and converts pricing quotes to confirmed sales contracts. In our real-life scenario, TechVenture Solutions Inc. inquires about purchasing high-grade Enterprise Server Racks.",
        "┌──────────────┐     ┌──────────────┐     ┌──────────────┐\n"
        "│  CRM Lead    │ ──> │ Draft Quote  │ ──> │ Confirmed SO │\n"
        "│ TechVenture  │     │ QT-2026-001  │     │ SO-2026-001  │\n"
        "└──────────────┘     └──────────────┘     └──────────────┘",
        [
            "Open 'CRM Leads' from the sidebar and click 'Add Lead'. Fill in client details for TechVenture Solutions Inc.",
            "Create a Quotation from the lead card, selecting the target product 'Enterprise Server Racks' (SKU: FG-SRV-42U).",
            "Set price, tax rate (GST 18%), and save. Once approved by the customer, click 'Convert to Sales Order'."
        ],
        ['Lead Reference', 'Prospect Customer', 'Target SKU', 'Qty Request', 'Quoted Price', 'Total Tax (18%)'],
        [
            ['LD-2026-081', 'TechVenture Solutions Inc.', 'FG-SRV-42U', '10 Units', '$2,500.00', '$4,500.00']
        ]
    )

    # Section 3: Sales
    add_section(
        "3. Sales & Customer Management",
        "Oversee customer directory records, Sales Orders (SO), invoice billing, and customer product return requests (RMA). Converting the quote creates Sales Order SO-2026-001, which locks the inventory allocation.",
        "┌──────────────┐     ┌──────────────┐     ┌──────────────┐\n"
        "│ Sales Order  │ ──> │ Generate Inv │ ──> │ Receive Cash │\n"
        "│ SO-2026-001  │     │ INV-2026-001 │     │ JV-2026-1050 │\n"
        "└──────┬───────┘     └──────────────┘     └──────────────┘\n"
        "       │ (Stock Allocation)\n"
        "       ▼\n"
        "┌──────────────┐\n"
        "│ Return/RMA   │\n"
        "│ RMA-2026-001 │\n"
        "└──────────────┘",
        [
            "Navigate to Sales -> Sales Orders. Check that SO-2026-001 is generated in draft.",
            "Verify TechVenture Solutions Inc.'s shipping address and update the order status to 'processing'.",
            "Click 'Generate Invoice' to issue invoice INV-2026-001. Mark as paid when payments arrive."
        ],
        ['Order ID', 'Invoice ID', 'Client Name', 'Total Billable', 'Payment Status', 'RMA Log'],
        [
            ['SO-2026-001', 'INV-2026-001', 'TechVenture Solutions', '$29,500.00', 'Unpaid (Pending)', 'RMA-2026-001 (Bezel)']
        ]
    )

    # Section 4: Inventory
    add_section(
        "4. Inventory & Warehouse Management (WMS)",
        "The Warehouse Management System (WMS) tracks real-time quantities of goods located inside physical bins, racks, zones, and warehouses. Items are organized in a strict logical tree to allow operators to find stock instantly.",
        "┌───────────────────────────────────────────────────────┐\n"
        "│              WMS PHYSICAL STORAGE HIERARCHY           │\n"
        "│                                                       │\n"
        "│ Central WH-01 ──> Zone Z-RAW ──> Rack R-03 ──> Bin A02 │\n"
        "│                                                       │\n"
        "│                  [Stock Take Audit ST-2026-04]        │\n"
        "│                System: 12  ──>  Counted: 10           │\n"
        "│                    Reconcile Discrepancy (-2)         │\n"
        "└───────────────────────────────────────────────────────┘",
        [
            "Open 'Inventory' -> 'Warehouses'. Review locations: Warehouse WH-01, Zone Z-RAW, Rack R-03, Bin A02-R2-S1.",
            "Use internal transfer to move components from receiving to assembly bins.",
            "Perform a physical Stock Take. If a discrepancy is found, submit counts to auto-adjust."
        ],
        ['Warehouse ID', 'Zone Name', 'Rack ID', 'Bin Address', 'SKU Stored', 'System Stock', 'Audit Variance'],
        [
            ['WH-01 (Central)', 'Z-RAW (Raw Materials)', 'R-03', 'A02-R2-S1', 'RM-STL-42U', '12 Units', '-2 (Adjusted)']
        ]
    )

    # Section 5: Procurement
    add_section(
        "5. Procurement (RFQs, POs, & GRN)",
        "Procurement manages supplier listings, bid tracking (RFQs), purchase orders (PO), and goods receiving (GRN). This initiates the flow by securing the raw steel frames required to assemble the server racks.",
        "┌──────────────┐     ┌──────────────┐     ┌──────────────┐\n"
        "│ Supplier RFQ │ ──> │ Purchase Order│ ──> │Goods Receipt │\n"
        "│ RFQ-2026-001 │     │ PO-2026-001  │     │ GRN-2026-001 │\n"
        "└──────────────┘     └──────────────┘     └──────┬───────┘\n"
        "                                                 │ (Auto)\n"
        "                                                 ▼\n"
        "                                          ┌──────────────┐\n"
        "                                          │ Pending QC   │\n"
        "                                          │ QC-2026-001  │\n"
        "                                          └──────────────┘",
        [
            "Create Supplier RFQ RFQ-2026-001. Send to Global Iron & Steel Co.",
            "Accept their quote and generate PO-2026-001 for 10 units of RM-STL-42U.",
            "When delivery arrives, click 'Receive Goods' to create GRN-2026-001, moving stock to bin A02-R2-S1."
        ],
        ['RFQ Reference', 'PO Reference', 'Awarded Supplier', 'SKU Ordered', 'Qty Received', 'GRN ID'],
        [
            ['RFQ-2026-001', 'PO-2026-001', 'Global Iron & Steel Co.', 'RM-STL-42U', '10 Units', 'GRN-2026-001']
        ]
    )

    # Section 6: Manufacturing
    add_section(
        "6. Manufacturing (BOM & MO)",
        "Set up product recipes (Bills of Materials) and execute Manufacturing Orders (MO) to assemble raw components into finished items. Completed MOs auto-consume component items and add finished products to inventory.",
        "┌──────────────────────────────────────────────┐\n"
        "│             BOM Recipe: FG-SRV-42U           │\n"
        "│       - 1x Steel Frame (RM-STL-42U)          │\n"
        "│       - 4x Cooling Fans (RM-FAN-120)         │\n"
        "│       - 1x Power Supply (RM-PWR-850)         │\n"
        "└──────────────────────┬───────────────────────┘\n"
        "                       │ (Create MO)\n"
        "                       ▼\n"
        "┌──────────────┐     ┌──────────────┐     ┌──────────────┐\n"
        "│  MO Confirmed│ ──> │ Production   │ ──> │ Finished SKU │\n"
        "│ MO-2026-001  │     │ Consumption  │     │ Target Bin   │\n"
        "└──────────────┘     └──────────────┘     └──────────────┘",
        [
            "Open Manufacturing -> Bills of Materials (BOM). Create recipe for finished product FG-SRV-42U.",
            "Go to Manufacturing Orders (MO). Click 'New Order', choose BOM and quantity 10, then confirm.",
            "Progress the MO status to 'Produce'. Upon completion, select destination bin A02-R3-S2."
        ],
        ['BOM Recipe ID', 'Manufacturing MO', 'Finished SKU', 'Target Qty', 'Raw Material Debits', 'Finished Goods Credits'],
        [
            ['BOM-FG-42U', 'MO-2026-001', 'FG-SRV-42U', '10 Units', '10x RM-STL-42U', '10x FG-SRV-42U']
        ]
    )

    # Section 7: Quality Control
    add_section(
        "7. Quality Control & Assurance (QC)",
        "Prevents defective inventory from being shipped to customers or placed into active warehouse storage. All incoming items (GRN) and completed assembly runs (MO) trigger pending QC audits.",
        "┌──────────────┐     ┌──────────────┐     ┌──────────────┐\n"
        "│ MO/GRN Event │ ──> │ Quality Check│ ──> │ Verdict      │\n"
        "│ (Trigger)    │     │ QC-2026-001  │     │ Passed!      │\n"
        "└──────────────┘     └──────────────┘     └──────┬───────┘\n"
        "                                                 │ (Release)\n"
        "                                                 ▼\n"
        "                                          ┌──────────────┐\n"
        "                                          │ Active Stock │\n"
        "                                          │ WH-01 Bin    │\n"
        "                                          └──────────────┘",
        [
            "Go to the Quality Audits dashboard. Locate the pending check triggered by MO-2026-001.",
            "Click 'Audit Release'. Review inspection points (dimensions, rivets, grounding).",
            "Register the verdict as 'Passed'. This releases the 10 units to active inventory."
        ],
        ['Audit Reference', 'Origin Document', 'Audited SKU', 'Inspector Name', 'QC Verdict', 'Reconciled Date'],
        [
            ['QC-2026-001', 'MO-2026-001', 'FG-SRV-42U', 'Alice Smith', 'Passed (Released)', '2026-06-07']
        ]
    )

    # Section 8: Fleet
    add_section(
        "8. Fleet, Shipments, & Logistics",
        "Coordinates transport operations, vehicle profiles, fuel tracking, and driver dispatch routines. The logistics engine manages shipment schedules and posts ledger transactions upon completion.",
        "┌──────────────┐     ┌──────────────┐     ┌──────────────┐\n"
        "│ Sales Order  │ ──> │ Ship Release │ ──> │ Dispatch     │\n"
        "│ SO-2026-001  │     │ SH-2026-001  │     │ Ford Transit │\n"
        "└──────────────┘     └──────────────┘     └──────┬───────┘\n"
        "                                                 │ (Dropoff)\n"
        "                                                 ▼\n"
        "                                          ┌──────────────┐\n"
        "                                          │ Status       │\n"
        "                                          │ Delivered    │\n"
        "                                          └──────────────┘",
        [
            "Open Logistics -> Dispatch Board. Check pending sales shipment SH-2026-001.",
            "Drag and assign the shipment to Driver John Doe and Delivery Van V-100.",
            "Mark shipment as 'Delivered' upon client arrival to trigger COGS ledger entries."
        ],
        ['Shipment ID', 'Driver Name', 'Vehicle ID', 'Destination Route', 'Delivery Status', 'COGS Debit'],
        [
            ['SH-2026-001', 'John Doe', 'V-100 (Ford Transit)', 'WH-01 to TechVenture HQ', 'Delivered', '$6,500.00']
        ]
    )

    # Section 9: Accounting
    add_section(
        "9. Financial Accounting & Chart of Accounts",
        "Provides compliance-ready ledger reporting. SCM ERP automatically generates standard double-entry journal postings as business actions occur, ensuring financial integrity.",
        "┌───────────────────────────────────────────────────────┐\n"
        "│               LEDGER ACCOUNT POSTING FLOW             │\n"
        "│                                                       │\n"
        "│  [Invoice INV-2026-001]                               │\n"
        "│     Debit: Accounts Receivable ($29,500.00)           │\n"
        "│     Credit: Sales Revenue ($25,000.00)                │\n"
        "│     Credit: GST Liability ($4,500.00)                 │\n"
        "│                                                       │\n"
        "│  [Shipment SH-2026-001]                               │\n"
        "│     Debit: Cost of Goods Sold ($6,500.00)             │\n"
        "│     Credit: Finished Goods Inventory ($6,500.00)      │\n"
        "└───────────────────────────────────────────────────────┘",
        [
            "Navigate to Finance -> General Ledger to view the double-entry transactions log.",
            "Open Chart of Accounts. Observe assets incremented and liabilities cleared.",
            "Use filters to review balance sheets and verify that debits equal credits."
        ],
        ['Journal ID', 'Posting Date', 'Account Name', 'Debit Amount', 'Credit Amount', 'Transaction Description'],
        [
            ['JV-2026-1049', '2026-06-07', 'Accounts Receivable', '$29,500.00', '$0.00', 'Invoice INV-2026-001 TechVenture'],
            ['JV-2026-1049', '2026-06-07', 'Sales Revenue', '$0.00', '$25,000.00', 'Invoice INV-2026-001 TechVenture'],
            ['JV-2026-1049', '2026-06-07', 'GST Liabilities', '$0.00', '$4,500.00', 'Invoice INV-2026-001 TechVenture'],
            ['JV-2026-1050', '2026-06-07', 'Cash & Bank', '$29,500.00', '$0.00', 'Payment received from TechVenture'],
            ['JV-2026-1050', '2026-06-07', 'Accounts Receivable', '$0.00', '$29,500.00', 'Payment received from TechVenture'],
            ['JV-2026-1051', '2026-06-07', 'Cost of Goods Sold', '$6,500.00', '$0.00', 'COGS for shipment SH-2026-001'],
            ['JV-2026-1051', '2026-06-07', 'Finished Goods Inventory', '$0.00', '$6,500.00', 'Inventory deduction for SH-2026-001']
        ]
    )

    # Section 10: AI Forecasting
    add_section(
        "10. Enterprise AI & Demand Forecasting",
        "Optimize stock levels and prevent cash flow blocks with automated predictive demand algorithms. By calculating daily average usage, the forecasting module alerts staff and generates replenishment suggestions.",
        "┌──────────────┐     ┌──────────────┐     ┌──────────────┐\n"
        "│ Consumption  │ ──> │ Safety Stock │ ──> │ Suggest PO   │\n"
        "│ Velocity     │     │ Multiplier   │     │ PO-2026-002  │\n"
        "│ 1.2 units/day│     │ Slider 130%  │     │ Draft Qty 10 │\n"
        "└──────────────┘     └──────────────┘     └──────────────┘",
        [
            "Open AI Forecasting. Review consumption velocity calculations.",
            "Adjust safety stock multiplier (e.g. 130%) to simulate peak seasons.",
            "Click 'Apply Safety Settings' and then 'Bulk Auto-Generate POs' to create drafts."
        ],
        ['Product SKU', 'Avg Daily Velocity', 'Lead Time (Days)', 'Safety Multiplier', 'Current Stock Level', 'Replenish PO'],
        [
            ['RM-STL-42U', '1.2 Units', '3 Days', '130% (Seasonality)', '2 Units (Alert)', 'PO-2026-002 (Draft)']
        ]
    )

    # Save
    doc.save(output_path)
    print("DOCX User Manual generated successfully at:", output_path)

if __name__ == '__main__':
    out_dir = '/Applications/XAMPP/xamppfiles/htdocs/scm-erp/public'
    if not os.path.exists(out_dir):
        os.makedirs(out_dir)
    create_manual(os.path.join(out_dir, 'user_manual.docx'))
