<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment->payment_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 16px; margin-bottom: 24px; }
        .title { font-size: 24px; font-weight: bold; margin: 0; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px 0; vertical-align: top; }
        .label { color: #6b7280; width: 34%; }
        .amount { font-size: 22px; font-weight: bold; color: #047857; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Payment Receipt</p>
        <div class="muted">{{ $payment->payment_number }}</div>
    </div>

    <div class="box">
        <table>
            <tr>
                <td class="label">Invoice</td>
                <td>{{ $payment->invoice?->invoice_number ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Student</td>
                <td>{{ $payment->studentProfile?->user?->name ?? '-' }} / {{ $payment->studentProfile?->nim ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Study Program</td>
                <td>{{ $payment->studentProfile?->studyProgram?->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Payment Method</td>
                <td>{{ str($payment->payment_method)->replace('_', ' ')->title() }}</td>
            </tr>
            <tr>
                <td class="label">Transaction Reference</td>
                <td>{{ $payment->transaction_reference ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Paid At</td>
                <td>{{ $payment->paid_at?->format('d F Y H:i') ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Verified At</td>
                <td>{{ $payment->verified_at?->format('d F Y H:i') ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Verified By</td>
                <td>{{ $payment->verifiedBy?->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <div class="muted">Amount Paid</div>
        <div class="amount">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</div>
    </div>

    @if($payment->verification_notes)
        <div class="box">
            <div class="muted">Verification Notes</div>
            <div>{{ $payment->verification_notes }}</div>
        </div>
    @endif
</body>
</html>
