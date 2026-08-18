<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333;
            font-size: 14px;
            line-height: 1.6;
        }
        .header {
            width: 100%;
            margin-bottom: 40px;
        }
        .header td {
            vertical-align: top;
        }
        .company-info {
            text-align: right;
            color: #555;
        }
        .company-info h2 {
            margin: 0;
            color: #111;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #111;
            text-transform: uppercase;
            margin: 0 0 5px 0;
        }
        .invoice-number {
            color: #777;
            font-size: 16px;
        }
        .meta-info {
            width: 100%;
            margin-bottom: 40px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .meta-info td {
            vertical-align: top;
            width: 50%;
        }
        .label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #888;
            margin: 0 0 5px 0;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table-items th {
            text-align: left;
            border-bottom: 2px solid #eee;
            padding: 10px 5px;
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
        }
        .table-items td {
            padding: 15px 5px;
            border-bottom: 1px solid #eee;
        }
        .text-right {
            text-align: right !important;
        }
        .totals {
            width: 100%;
        }
        .totals td {
            padding: 8px 5px;
        }
        .totals .row-label {
            width: 70%;
            text-align: right;
            color: #555;
        }
        .totals .row-value {
            width: 30%;
            text-align: right;
        }
        .total-row {
            font-size: 18px;
            font-weight: bold;
            color: #111;
        }
        .due-row {
            font-size: 22px;
            font-weight: bold;
            color: #ea580c; /* Orange */
        }
        .notes {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #555;
            font-size: 12px;
        }
        .notes strong {
            color: #333;
        }
        .watermark {
            position: fixed;
            top: 40%;
            left: 25%;
            font-size: 120px;
            font-weight: bold;
            color: #10b981; /* Emerald */
            opacity: 0.1;
            transform: rotate(-15deg);
            z-index: -1;
            text-transform: uppercase;
            border: 10px solid #10b981;
            padding: 10px 40px;
            border-radius: 20px;
        }
    </style>
</head>
<body>

    @if($invoice->status === 'paid')
        <div class="watermark">PAID</div>
    @endif

    <table class="header">
        <tr>
            <td>
                <h1 class="title">Invoice</h1>
                <div class="invoice-number">#{{ $invoice->invoice_number }}</div>
            </td>
            <td class="company-info">
                <h2>{{ $invoice->business->name ?? 'Company Name' }}</h2>
                @if($invoice->business && $invoice->business->address)
                    <div>{!! nl2br(e($invoice->business->address)) !!}</div>
                @endif
                @if($invoice->business && $invoice->business->tax_number)
                    <div>Tax ID: {{ $invoice->business->tax_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="meta-info">
        <tr>
            <td>
                <div class="label">Billed To:</div>
                <div style="font-weight: bold; font-size: 16px; color: #111;">{{ $invoice->client->name }}</div>
                @if($invoice->client->company_name)
                    <div>{{ $invoice->client->company_name }}</div>
                @endif
                @if($invoice->client->address)
                    <div>{!! nl2br(e($invoice->client->address)) !!}</div>
                @endif
                @if($invoice->client->email)
                    <div>{{ $invoice->client->email }}</div>
                @endif
                @if($invoice->client->phone)
                    <div>{{ $invoice->client->phone }}</div>
                @endif
            </td>
            <td class="text-right">
                <div style="margin-bottom: 15px;">
                    <div class="label">Issue Date:</div>
                    <div style="font-weight: bold;">{{ \Carbon\Carbon::parse($invoice->issue_date)->format('M d, Y') }}</div>
                </div>
                <div>
                    <div class="label">Due Date:</div>
                    <div style="font-weight: bold;">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="table-items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">₹{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="row-label">Subtotal</td>
            <td class="row-value">₹{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="row-label" style="border-bottom: 2px solid #eee; padding-bottom: 15px;">Tax @if($invoice->tax_rate > 0) ({{ number_format($invoice->tax_rate, 1) }}%) @endif</td>
            <td class="row-value" style="border-bottom: 2px solid #eee; padding-bottom: 15px;">₹{{ number_format($invoice->tax_total, 2) }}</td>
        </tr>
        <tr>
            <td class="row-label total-row" style="padding-top: 15px;">Total</td>
            <td class="row-value total-row" style="padding-top: 15px;">₹{{ number_format($invoice->total, 2) }}</td>
        </tr>
        
        @if($invoice->amount_paid > 0)
        <tr>
            <td class="row-label" style="color: #10b981; font-weight: bold;">Amount Paid</td>
            <td class="row-value" style="color: #10b981; font-weight: bold;">-₹{{ number_format($invoice->amount_paid, 2) }}</td>
        </tr>
        @endif
        
        <tr>
            <td class="row-label due-row" style="padding-top: 15px;">Amount Due</td>
            <td class="row-value due-row" style="padding-top: 15px;">₹{{ number_format($invoice->total - $invoice->amount_paid, 2) }}</td>
        </tr>
    </table>

    @if($invoice->notes)
        <div class="notes">
            <strong>Notes / Bank Details:</strong><br>
            {!! nl2br(e($invoice->notes)) !!}
        </div>
    @endif

</body>
</html>
