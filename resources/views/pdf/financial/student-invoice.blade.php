<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 16px; margin-bottom: 24px; }
        .title { font-size: 24px; font-weight: bold; margin: 0; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 0; vertical-align: top; }
        .label { color: #6b7280; width: 34%; }
        .amount { font-size: 22px; font-weight: bold; color: #111827; }
        .items th { text-align: left; border-bottom: 1px solid #e5e7eb; padding: 6px 4px; color: #6b7280; font-size: 11px; }
        .items td { border-bottom: 1px solid #f3f4f6; padding: 7px 4px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Student Invoice</p>
        <div class="muted">{{ $invoice->invoice_number }} &middot; {{ $invoice->status }}</div>
    </div>

    <div class="box">
        <table>
            <tr>
                <td class="label">Student</td>
                <td>{{ $invoice->studentProfile?->user?->name ?? '-' }} / {{ $invoice->studentProfile?->nim ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Study Program</td>
                <td>{{ $invoice->studentProfile?->studyProgram?->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Academic Year</td>
                <td>{{ $invoice->academicYear?->name ?? '-' }}{{ $invoice->semester ? ' &middot; Semester '.$invoice->semester : '' }}</td>
            </tr>
            <tr>
                <td class="label">Invoice Type</td>
                <td>{{ $invoice->invoice_type }}</td>
            </tr>
            <tr>
                <td class="label">Due Date</td>
                <td>{{ $invoice->due_date?->format('d F Y') ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Issued</td>
                <td>{{ $invoice->issued_at?->format('d F Y H:i') ?? '-' }} {{ $invoice->issuedBy?->name ? ' by '.$invoice->issuedBy->name : '' }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }} <span class="muted">({{ $item->item_type }})</span></td>
                        <td class="right">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                @foreach($invoice->adjustments as $adjustment)
                    <tr>
                        <td>{{ $adjustment->adjustment_type }} <span class="muted">({{ $adjustment->reason ?: 'adjustment' }})</span></td>
                        <td class="right">Rp {{ number_format((float) $adjustment->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="box">
        <table>
            <tr>
                <td class="label">Total</td>
                <td class="amount">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Paid</td>
                <td>Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Outstanding</td>
                <td><strong>Rp {{ number_format((float) $invoice->outstanding_amount, 0, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    @if($invoice->notes)
        <div class="box">
            <div class="muted">Notes</div>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif
</body>
</html>
