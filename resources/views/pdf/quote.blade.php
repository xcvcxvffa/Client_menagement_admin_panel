<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->quote_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
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
        .quote-number {
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
            margin-bottom: 40px;
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
            padding: 10px 5px;
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
            font-size: 20px;
            font-weight: bold;
            color: #111;
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
    </style>
</head>
<body>

    <table class="header">
        <tr>
            <td>
                <h1 class="title">Quotation</h1>
                <div class="quote-number">#{{ $quote->quote_number }}</div>
            </td>
            <td class="company-info">
                <h2>FreelanceHub Inc.</h2>
                <div>123 Business Road</div>
                <div>Tech City, TX 75001</div>
                <div>hello@freelancehub.local</div>
            </td>
        </tr>
    </table>

    <table class="meta-info">
        <tr>
            <td>
                <div class="label">Quote To:</div>
                <div style="font-weight: bold; font-size: 16px; color: #111;">{{ $quote->client->name }}</div>
                @if($quote->client->company_name)
                    <div>{{ $quote->client->company_name }}</div>
                @endif
                @if($quote->client->email)
                    <div>{{ $quote->client->email }}</div>
                @endif
                @if($quote->client->phone)
                    <div>{{ $quote->client->phone }}</div>
                @endif
            </td>
            <td class="text-right">
                <div style="margin-bottom: 15px;">
                    <div class="label">Date Issued:</div>
                    <div style="font-weight: bold;">{{ $quote->created_at->format('M d, Y') }}</div>
                </div>
                <div>
                    <div class="label">Valid Until:</div>
                    <div style="font-weight: bold;">{{ \Carbon\Carbon::parse($quote->valid_until)->format('M d, Y') }}</div>
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
            @foreach($quote->items as $item)
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
            <td class="row-value">₹{{ number_format($quote->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="row-label" style="border-bottom: 2px solid #eee; padding-bottom: 15px;">Tax</td>
            <td class="row-value" style="border-bottom: 2px solid #eee; padding-bottom: 15px;">₹{{ number_format($quote->tax_total, 2) }}</td>
        </tr>
        <tr>
            <td class="row-label total-row" style="padding-top: 15px;">TOTAL</td>
            <td class="row-value total-row" style="padding-top: 15px;">₹{{ number_format($quote->total, 2) }}</td>
        </tr>
    </table>

    @if($quote->notes)
        <div class="notes">
            <strong>Terms & Conditions:</strong><br>
            {!! nl2br(e($quote->notes)) !!}
        </div>
    @endif

</body>
</html>
