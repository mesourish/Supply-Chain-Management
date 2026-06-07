<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $type }} #{{ $model->id }}</title>
    <style>
        @page {
            margin: 310px 40px 70px 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #333;
            line-height: 1.4;
        }
        header {
            position: fixed;
            top: -280px;
            left: 0px;
            right: 0px;
            height: 250px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 10px;
        }
        footer {
            position: fixed;
            bottom: -45px;
            left: 0px;
            right: 0px;
            height: 35px;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
        main {
            /* Main Content Container */
        }
        
        .header-content { width: 100%; display: table; margin-bottom: 10px; }
        .header-left { display: table-cell; vertical-align: middle; width: 50%; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 50%; }
        
        .header-left img { max-height: 50px; margin-bottom: 5px; }
        .company-name { font-size: 20px; font-weight: bold; color: #111; margin: 0; }
        .doc-title { font-size: 28px; font-weight: 900; color: #4f46e5; text-transform: uppercase; margin: 0; letter-spacing: 1px; }
        .doc-meta { font-size: 12px; color: #666; margin-top: 5px; }
        
        .info-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .info-table td { vertical-align: top; width: 50%; }
        .info-panel { background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; margin-right: 10px; }
        .info-panel-right { margin-right: 0; margin-left: 10px; }
        
        .section-title { font-size: 11px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .items-table th, .items-table td { border-bottom: 1px solid #e2e8f0; padding: 12px 10px; text-align: left; }
        .items-table th { background-color: #f1f5f9; font-weight: bold; color: #334155; font-size: 12px; text-transform: uppercase; }
        .items-table tr:nth-child(even) { background-color: #f8fafc; }
        
        .totals-wrapper { width: 100%; display: table; page-break-inside: avoid; }
        .remarks-col { display: table-cell; width: 55%; vertical-align: top; padding-right: 20px; }
        .totals-col { display: table-cell; width: 45%; vertical-align: top; }
        
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 8px 10px; text-align: right; border-bottom: 1px solid #f1f5f9; }
        .totals-table .bold { font-weight: bold; color: #0f172a; }
        .totals-table .grand-total { font-size: 18px; font-weight: 900; color: #4f46e5; border-bottom: none; border-top: 2px solid #e2e8f0; padding-top: 12px; }
        
        .remarks-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; min-height: 80px; }
        .remarks-text { font-size: 12px; color: #475569; }
        
        .amount-words-box { margin-top: 15px; padding: 12px; background: #eef2ff; border: 1px solid #e0e7ff; border-radius: 6px; font-size: 12px; font-weight: bold; color: #4f46e5; }
        
        .signatures { width: 100%; display: table; margin-top: 40px; page-break-inside: avoid; }
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
                    <strong>Ref #:</strong> {{ $type === 'Invoice' ? 'INV' : ($type === 'Sales Order' ? 'SO' : ($type === 'Quotation' ? 'QT' : 'PO')) }}-{{ str_pad($model->id, 5, '0', STR_PAD_LEFT) }}<br>
                    <strong>Date:</strong> {{ $model->created_at->format('M d, Y') }}<br>
                    <strong>Status:</strong> <span style="text-transform: uppercase; font-weight: bold; color: {{ $model->status === 'paid' || $model->status === 'received' || $model->status === 'shipped' ? '#10b981' : '#f59e0b' }}">{{ $model->status }}</span>
                </div>
            </div>
        </div>
        
        <table class="info-table">
            <tr>
                <td>
                    <div class="info-panel">
                        <div class="section-title">
                            @if($type === 'Purchase Order')
                                Vendor / Supplier
                            @else
                                Bill To Customer / Lead
                            @endif
                        </div>
                        @if($type === 'Purchase Order' && $model->supplier)
                            <strong>{{ $model->supplier->name }}</strong><br>
                            @if($model->supplier->contact_person) Attn: {{ $model->supplier->contact_person }}<br> @endif
                            @if($model->supplier->address) {!! nl2br(e(Str::limit($model->supplier->address, 120))) !!}<br> @endif
                            @if($model->supplier->phone) {{ $model->supplier->phone }} @endif
                        @elseif(($type === 'Sales Order' || $type === 'Invoice' || $type === 'Quotation') && $model->customer)
                            <strong>{{ $model->customer->name }}</strong><br>
                            @if($model->customer->contact_person) Attn: {{ $model->customer->contact_person }}<br> @endif
                            @if($model->customer->billing_address) {!! nl2br(e(Str::limit($model->customer->billing_address, 120))) !!}<br> @endif
                            @if($model->customer->phone) {{ $model->customer->phone }} @endif
                        @elseif($type === 'Quotation' && $model->lead)
                            <strong>{{ $model->lead->company_name ?? $model->lead->name }}</strong><br>
                            @if($model->lead->name) Attn: {{ $model->lead->name }}<br> @endif
                            @if($model->lead->address) {!! nl2br(e(Str::limit($model->lead->address, 120))) !!}<br> @endif
                            @if($model->lead->phone) {{ $model->lead->phone }} @endif
                        @elseif($type === 'Invoice' && $model->salesOrder && $model->salesOrder->customer)
                            <strong>{{ $model->salesOrder->customer->name }}</strong><br>
                            @if($model->salesOrder->customer->contact_person) Attn: {{ $model->salesOrder->customer->contact_person }}<br> @endif
                            @if($model->salesOrder->customer->billing_address) {!! nl2br(e(Str::limit($model->salesOrder->customer->billing_address, 120))) !!}<br> @endif
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
                            {!! nl2br(e(Str::limit($model->customer->shipping_address, 150))) !!}
                        @elseif($type === 'Invoice' && $model->salesOrder && $model->salesOrder->customer && $model->salesOrder->customer->shipping_address)
                            <strong>Delivery Address:</strong><br>
                            {!! nl2br(e(Str::limit($model->salesOrder->customer->shipping_address, 150))) !!}
                        @else
                            As per standard terms.
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <div class="footer-content">
            <p style="margin:0;">{{ setting('website_name', 'SCM ERP System') }} &copy; {{ date('Y') }}. All rights reserved. | Page <span class="page-number"></span></p>
        </div>
    </footer>

    <main>
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
            
            // Amount in words spellout logic
            if (!function_exists('amountToWords')) {
                function amountToWords($amt, $currencyCode = 'USD') {
                    $currencyCode = strtoupper($currencyCode);
                    
                    $currencies = [
                        'USD' => ['major' => 'US Dollar', 'majors' => 'US Dollars', 'minor' => 'Cent', 'minors' => 'Cents'],
                        'EUR' => ['major' => 'Euro', 'majors' => 'Euros', 'minor' => 'Cent', 'minors' => 'Cents'],
                        'GBP' => ['major' => 'Pound', 'majors' => 'Pounds', 'minor' => 'Penny', 'minors' => 'Pence'],
                        'AED' => ['major' => 'Dirham', 'majors' => 'Dirhams', 'minor' => 'Fils', 'minors' => 'Fils'],
                        'INR' => ['major' => 'Rupee', 'majors' => 'Rupees', 'minor' => 'Paisa', 'minors' => 'Paise'],
                    ];
                    
                    $names = $currencies[$currencyCode] ?? ['major' => $currencyCode, 'majors' => $currencyCode, 'minor' => 'Cent', 'minors' => 'Cents'];
                    
                    $amt = round($amt, 2);
                    $whole = floor($amt);
                    $fraction = round(($amt - $whole) * 100);
                    
                    if (class_exists('\NumberFormatter')) {
                        $formatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
                        $wholeWords = $formatter->format($whole);
                        $wholeWords = ucwords($wholeWords);
                        
                        $result = $wholeWords . ' ' . ($whole == 1 ? $names['major'] : $names['majors']);
                        
                        if ($fraction > 0) {
                            $fractionWords = $formatter->format($fraction);
                            $fractionWords = ucwords($fractionWords);
                            $result .= ' and ' . $fractionWords . ' ' . ($fraction == 1 ? $names['minor'] : $names['minors']);
                        }
                    } else {
                        // Pure PHP spellout fallback when php-intl extension is missing
                        $spellout = function($num) use (&$spellout) {
                            $ones = [
                                0 => "Zero", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four",
                                5 => "Five", 6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine",
                                10 => "Ten", 11 => "Eleven", 12 => "Twelve", 13 => "Thirteen",
                                14 => "Fourteen", 15 => "Fifteen", 16 => "Sixteen", 17 => "Seventeen",
                                18 => "Eighteen", 19 => "Nineteen"
                            ];
                            $tens = [
                                0 => "Zero", 1 => "Ten", 2 => "Twenty", 3 => "Thirty", 4 => "Forty",
                                5 => "Fifty", 6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
                            ];
                            $units = ["", "Thousand", "Million", "Billion"];

                            $num = (int)$num;
                            if ($num === 0) return "Zero";

                            $parts = [];
                            while ($num > 0) {
                                $parts[] = $num % 1000;
                                $num = (int)($num / 1000);
                            }

                            $words = [];
                            foreach ($parts as $i => $part) {
                                if ($part == 0) continue;
                                
                                $partWords = [];
                                $hundreds = (int)($part / 100);
                                $remainder = $part % 100;

                                if ($hundreds > 0) {
                                    $partWords[] = $ones[$hundreds] . " Hundred";
                                }

                                if ($remainder > 0) {
                                    if ($remainder < 20) {
                                        $partWords[] = $ones[$remainder];
                                    } else {
                                        $t = (int)($remainder / 10);
                                        $o = $remainder % 10;
                                        $partWords[] = $tens[$t] . ($o > 0 ? " " . $ones[$o] : "");
                                    }
                                }

                                $wordPart = implode(" ", $partWords);
                                if (isset($units[$i]) && $units[$i] !== "") {
                                    $wordPart .= " " . $units[$i];
                                }
                                array_unshift($words, $wordPart);
                            }

                            return implode(" ", $words);
                        };

                        $wholeWords = $spellout($whole);
                        $result = $wholeWords . ' ' . ($whole == 1 ? $names['major'] : $names['majors']);
                        
                        if ($fraction > 0) {
                            $fractionWords = $spellout($fraction);
                            $result .= ' and ' . $fractionWords . ' ' . ($fraction == 1 ? $names['minor'] : $names['minors']);
                        }
                    }
                    
                    return $result . ' Only';
                }
            }
            
            $currencyCode = $model->currency_code ?? setting('default_currency_code', 'USD');
            $words = amountToWords($total, $currencyCode);
        @endphp

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Item Description</th>
                    <th style="width: 12%; text-align: center;">Quantity</th>
                    <th style="width: 14%; text-align: right;">Unit Price</th>
                    <th style="width: 17%; text-align: right;">Total</th>
                    <!-- <th style="width: 17%; text-align: right;">Total</th> -->
                </tr>
            </thead>
            <tbody>
                @foreach($model->items as $index => $item)
                    @php
                        $lineSub = $item->quantity * $item->unit_price;
                        $taxShare = $subtotal > 0 ? ($lineSub / $subtotal) * $tax : 0;
                        $lineTot = $lineSub ;
                    @endphp
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
                        <td style="text-align: right;">{{ setting('currency_symbol', '$') }}{{ number_format($lineSub, 2) }}</td>
                        <!-- <td style="text-align: right; font-weight: bold;">{{ setting('currency_symbol', '$') }}{{ number_format($lineTot, 2) }}</td> -->
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Wrap Totals and Signatures to render together at the end of the last page flow -->
        <div class="last-page-bottom" style="page-break-inside: avoid;">
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
                    
                    <div class="amount-words-box">
                        Total in Words: <span style="color:#0f172a; font-style:italic;">{{ $words }}</span>
                    </div>
                </div>
                <div class="totals-col">
                    <table class="totals-table">
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
            
            <!-- Signatures -->
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
        </div>
    </main>
</body>
</html>
