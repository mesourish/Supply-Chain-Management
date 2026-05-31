<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $type }} #{{ $model->id }}</title>
    <style>
        @page {
            margin: 160px 40px 180px 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #333;
            line-height: 1.4;
        }
        header {
            position: fixed;
            top: -120px;
            left: 0px;
            right: 0px;
            height: 100px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 10px;
        }
        footer {
            position: fixed;
            bottom: -150px;
            left: 0px;
            right: 0px;
            height: 130px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        main {
            /* Main Content Container */
        }
        
        .header-content { width: 100%; display: table; }
        .header-left { display: table-cell; vertical-align: middle; width: 50%; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 50%; }
        
        .header-left img { max-height: 50px; margin-bottom: 5px; }
        .company-name { font-size: 20px; font-weight: bold; color: #111; margin: 0; }
        .doc-title { font-size: 28px; font-weight: 900; color: #4f46e5; text-transform: uppercase; margin: 0; letter-spacing: 1px; }
        .doc-meta { font-size: 12px; color: #666; margin-top: 5px; }
        
        .info-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .info-table td { vertical-align: top; width: 50%; }
        .info-panel { background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; margin-right: 10px; }
        .info-panel-right { margin-right: 0; margin-left: 10px; }
        
        .section-title { font-size: 11px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .items-table th, .items-table td { border-bottom: 1px solid #e2e8f0; padding: 12px 10px; text-align: left; }
        .items-table th { background-color: #f1f5f9; font-weight: bold; color: #334155; font-size: 12px; text-transform: uppercase; }
        .items-table tr:nth-child(even) { background-color: #f8fafc; }
        
        .totals-wrapper { width: 100%; display: table; }
        .remarks-col { display: table-cell; width: 55%; vertical-align: top; padding-right: 20px; }
        .totals-col { display: table-cell; width: 45%; vertical-align: top; }
        
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 8px 10px; text-align: right; border-bottom: 1px solid #f1f5f9; }
        .totals-table .bold { font-weight: bold; color: #0f172a; }
        .totals-table .grand-total { font-size: 18px; font-weight: 900; color: #4f46e5; border-bottom: none; border-top: 2px solid #e2e8f0; padding-top: 12px; }
        
        .remarks-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; min-height: 80px; }
        .remarks-text { font-size: 12px; color: #475569; }
        
        .signatures { width: 100%; display: table; margin-top: 15px; }
        .sig-block { display: table-cell; width: 33.3%; text-align: center; }
        .sig-line { border-bottom: 1px solid #64748b; margin: 0 20px 5px 20px; height: 40px; }
        .sig-name { font-size: 11px; color: #475569; text-transform: uppercase; font-weight: bold; }
        .sig-meta { font-size: 10px; color: #94a3b8; }
        
        .footer-content { text-align: center; font-size: 11px; color: #94a3b8; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>

    <header>
        <div class="header-content">
            <div class="header-left">
                @if(setting('website_logo'))
                    <img src="{{ setting('website_logo') }}" alt="Logo">
                @else
                    <h1 class="company-name">{{ setting('website_name', 'SCM ERP System') }}</h1>
                @endif
                <div style="font-size: 12px; color: #64748b; margin-top: 5px;">
                    {{ setting('company_location', "123 Enterprise Way\nBusiness City, ST 12345") }}
                </div>
            </div>
            <div class="header-right">
                <h1 class="doc-title">{{ $type }}</h1>
                <div class="doc-meta">
                    <strong>Ref #:</strong> {{ $type === 'Invoice' ? 'INV' : ($type === 'Sales Order' ? 'SO' : 'PO') }}-{{ str_pad($model->id, 5, '0', STR_PAD_LEFT) }}<br>
                    <strong>Date:</strong> {{ $model->created_at->format('M d, Y') }}<br>
                    <strong>Status:</strong> <span style="text-transform: uppercase; font-weight: bold; color: {{ $model->status === 'paid' || $model->status === 'received' || $model->status === 'shipped' ? '#10b981' : '#f59e0b' }}">{{ $model->status }}</span>
                </div>
            </div>
        </div>
    </header>

    <footer>
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name">Prepared By</div>
                <div class="sig-meta">Authorized Representative</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name">Approved By</div>
                <div class="sig-meta">
                    @if($type === 'Purchase Order' && $model->approval_status === 'approved')
                        Director / Management
                    @else
                        Department Head
                    @endif
                </div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name">Accepted By</div>
                <div class="sig-meta">Client / Vendor Signature</div>
            </div>
        </div>
        <div class="footer-content" style="margin-top: 15px;">
            <p style="margin:0;">{{ setting('website_name', 'SCM ERP System') }} &copy; {{ date('Y') }}. All rights reserved.</p>
            <p style="margin:0;">Page <span class="page-number"></span></p>
        </div>
    </footer>

    <main>
        <table class="info-table">
            <tr>
                <td>
                    <div class="info-panel">
                        <div class="section-title">
                            @if($type === 'Purchase Order')
                                Vendor / Supplier
                            @else
                                Bill To Customer
                            @endif
                        </div>
                        @if($type === 'Purchase Order' && $model->supplier)
                            <strong>{{ $model->supplier->name }}</strong><br>
                            @if($model->supplier->contact_person) Attn: {{ $model->supplier->contact_person }}<br> @endif
                            @if($model->supplier->address) {!! nl2br(e($model->supplier->address)) !!}<br> @endif
                            @if($model->supplier->email) {{ $model->supplier->email }}<br> @endif
                            @if($model->supplier->phone) {{ $model->supplier->phone }} @endif
                        @elseif(($type === 'Sales Order' || $type === 'Invoice') && $model->customer)
                            <strong>{{ $model->customer->name }}</strong><br>
                            @if($model->customer->contact_person) Attn: {{ $model->customer->contact_person }}<br> @endif
                            @if($model->customer->billing_address) {!! nl2br(e($model->customer->billing_address)) !!}<br> @endif
                            @if($model->customer->email) {{ $model->customer->email }}<br> @endif
                            @if($model->customer->phone) {{ $model->customer->phone }} @endif
                        @elseif($type === 'Invoice' && $model->salesOrder && $model->salesOrder->customer)
                            <strong>{{ $model->salesOrder->customer->name }}</strong><br>
                            @if($model->salesOrder->customer->contact_person) Attn: {{ $model->salesOrder->customer->contact_person }}<br> @endif
                            @if($model->salesOrder->customer->billing_address) {!! nl2br(e($model->salesOrder->customer->billing_address)) !!}<br> @endif
                            @if($model->salesOrder->customer->email) {{ $model->salesOrder->customer->email }}<br> @endif
                            @if($model->salesOrder->customer->phone) {{ $model->salesOrder->customer->phone }} @endif
                        @else
                            <span style="color:#94a3b8;">N/A</span>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="info-panel info-panel-right">
                        <div class="section-title">Shipping Details</div>
                        @if(($type === 'Sales Order' || $type === 'Invoice') && $model->customer && $model->customer->shipping_address)
                            <strong>Delivery Address:</strong><br>
                            {!! nl2br(e($model->customer->shipping_address)) !!}
                        @elseif($type === 'Invoice' && $model->salesOrder && $model->salesOrder->customer && $model->salesOrder->customer->shipping_address)
                            <strong>Delivery Address:</strong><br>
                            {!! nl2br(e($model->salesOrder->customer->shipping_address)) !!}
                        @else
                            As per standard terms.
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Item Description</th>
                    <th style="width: 15%; text-align: center;">Quantity</th>
                    <th style="width: 15%; text-align: right;">Unit Price</th>
                    <th style="width: 20%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($model->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->product ? $item->product->name : $item->description }}</strong>
                            @if($item->product && $item->product->sku)
                                <br><span style="font-size: 11px; color: #64748b;">SKU: {{ $item->product->sku }}</span>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: right;">{{ setting('currency_symbol', '$') }}{{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align: right; font-weight: bold;">{{ setting('currency_symbol', '$') }}{{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-wrapper">
            <div class="remarks-col">
                <div class="remarks-box">
                    <div class="section-title">Notes / Remarks</div>
                    <div class="remarks-text">
                        @if($model->description)
                            {!! nl2br(e($model->description)) !!}
                        @elseif($model->remarks)
                            {!! nl2br(e($model->remarks)) !!}
                        @else
                            Thank you for your business. Please process payment according to the terms listed.
                        @endif
                    </div>
                </div>
            </div>
            <div class="totals-col">
                <table class="totals-table">
                    @php
                        $subtotal = 0;
                        foreach($model->items as $item) {
                            $subtotal += ($item->quantity * $item->unit_price);
                        }
                        
                        $tax = 0;
                        if(isset($model->tax_amount)) $tax = $model->tax_amount;
                        elseif(isset($model->gst_amount)) $tax = $model->gst_amount;
                        
                        $shipping = $model->shipping_amount ?? 0;
                        $total = $model->amount ?? ($model->total_amount ?? ($subtotal + $tax + $shipping));
                    @endphp
                    <tr>
                        <td>Subtotal:</td>
                        <td class="bold">{{ setting('currency_symbol', '$') }}{{ number_format($subtotal, 2) }}</td>
                    </tr>
                    @if($tax > 0)
                    <tr>
                        <td>
                            Tax / GST
                            @if(isset($model->gst_percentage) && $model->gst_percentage > 0)
                                ({{ $model->gst_percentage }}%)
                            @endif
                            :
                        </td>
                        <td class="bold">{{ setting('currency_symbol', '$') }}{{ number_format($tax, 2) }}</td>
                    </tr>
                    @endif
                    @if($shipping > 0)
                    <tr>
                        <td>Shipping & Handling:</td>
                        <td class="bold">{{ setting('currency_symbol', '$') }}{{ number_format($shipping, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="grand-total">Total {{ $type === 'Invoice' ? 'Due' : 'Amount' }}:</td>
                        <td class="grand-total">{{ setting('currency_symbol', '$') }}{{ number_format($total, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
