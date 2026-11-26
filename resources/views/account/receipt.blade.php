@php
    $charge = $payment->charge;
    $membership = $payment->user->membership;
    $balance = $charge->balance();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Receipt {{ $payment->receipt_number }} · {{ config('app.name') }}</title>
    {{-- Standalone so it prints the same everywhere; colours follow the email letterhead. --}}
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f5fa; color: #2b2f5c; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .sheet { max-width: 40rem; margin: 2rem auto; background: #fff; border: 1px solid #d2d5e6; }
        .band { background: #080250; padding: 1.25rem 1.5rem; }
        .band img { display: block; width: 150px; height: auto; }
        .body { padding: 1.75rem 1.5rem 2rem; }
        h1 { margin: 0; color: #080250; font-size: 1.5rem; }
        .number { margin: .25rem 0 0; color: #555a7b; font-variant-numeric: tabular-nums; }
        .amount { margin: 1.5rem 0 0; color: #080250; font-size: 2.25rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        dl { display: grid; grid-template-columns: minmax(7rem, 11rem) 1fr; gap: .5rem 1rem; margin: 1.5rem 0 0; padding-top: 1.25rem; border-top: 1px solid #d2d5e6; }
        dt { color: #555a7b; }
        dd { margin: 0; font-variant-numeric: tabular-nums; }
        .foot { margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #d2d5e6; color: #555a7b; font-size: .875rem; }
        .print { margin-top: 1.5rem; font: inherit; font-weight: 600; color: #fff; background: #080250; border: 0; border-radius: .5rem; padding: .625rem 1.125rem; cursor: pointer; }
        .print:focus-visible { outline: 3px solid #c8003f; outline-offset: 2px; }
        @media (max-width: 30rem) { .sheet { margin: 0; border: 0; } dl { grid-template-columns: 1fr; gap: 0; } dd { margin-bottom: .5rem; } }
        @media print { body { background: #fff; } .sheet { margin: 0; border: 0; } .print { display: none; } .band { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <main class="sheet">
        <div class="band"><img src="{{ asset('images/oasis-logo-white.png') }}" width="150" height="56" alt="{{ config('app.name') }}"></div>
        <div class="body">
            <h1>Receipt</h1>
            <p class="number">{{ $payment->receipt_number }}</p>
            <p class="amount">₦{{ number_format($payment->amount) }}</p>
            <dl>
                <dt>Received from</dt>
                <dd>{{ $payment->user->name }}@if ($membership)<br>{{ $membership->number }}@endif</dd>
                <dt>For</dt>
                <dd>{{ $charge->label() }}</dd>
                <dt>Paid by</dt>
                <dd>{{ $payment->method->label() }}@if ($payment->reference), reference {{ $payment->reference }}@endif</dd>
                <dt>Date paid</dt>
                <dd>{{ $payment->paid_on->format('j F Y') }}</dd>
                <dt>Charge</dt>
                <dd>₦{{ number_format($charge->amount) }}@if ($charge->waived_amount > 0), of which ₦{{ number_format($charge->waived_amount) }} waived @endif</dd>
                <dt>Still to pay today</dt>
                <dd>{{ $balance === 0 ? 'Nothing; this charge is settled' : '₦'.number_format($balance) }}</dd>
            </dl>
            <p class="foot">{{ config('app.name') }}. Issued {{ $payment->created_at->format('j F Y') }}. Keep this receipt for your records.</p>
            <button type="button" class="print" onclick="window.print()">Print or save as PDF</button>
        </div>
    </main>
</body>
</html>
