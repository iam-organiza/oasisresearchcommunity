@php
    $charge = $payment->charge;
    $user = $payment->user;
    $membership = $user->membership;
    // The ledger as it stood when this payment was made, so a reprint never changes:
    // payments recorded before this one, then this one, then what was left.
    $earlier = $charge->payments
        ->filter(fn ($other) => $other->id < $payment->id && $other->voided_at === null)
        ->sortBy('id');
    $left = max(0, $charge->amount - $charge->waived_amount - $earlier->sum('amount') - $payment->amount);
    $notDueYet = $charge->due_on?->isFuture() === true;
    $site = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');
    $naira = fn (int $amount) => '₦'.number_format($amount);
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
        :root { color-scheme: light; --navy: #080250; --ink: #2b2f5c; --muted: #555a7b; --line: #d2d5e6; --paper: #f4f5fa; --crimson: #c8003f; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--paper); color: var(--ink); font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .sheet { max-width: 46rem; margin: 2rem auto; background: #fff; border: 1px solid var(--line); }
        .band { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: var(--navy); padding: 1.25rem 2rem; color: #c9ccee; font-size: .875rem; }
        .band img { display: block; width: 150px; height: auto; }
        .body { padding: 2rem; }
        .title { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .25rem 1rem; }
        h1 { margin: 0; color: var(--navy); font-size: 1.75rem; line-height: 1.2; }
        .number { margin: .25rem 0 0; color: var(--muted); }
        .date { margin: 0; color: var(--muted); text-align: right; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.75rem; }
        .parties h2 { margin: 0 0 .25rem; color: var(--muted); font-size: .75rem; font-weight: 600; letter-spacing: .02em; }
        .parties p { margin: 0; }
        .parties strong { color: var(--navy); }
        table { width: 100%; margin-top: 1.75rem; border-collapse: collapse; }
        th { padding: .5rem 0; color: var(--muted); font-size: .75rem; font-weight: 600; text-align: left; border-bottom: 1px solid var(--line); }
        th:last-child, td:last-child { text-align: right; white-space: nowrap; padding-left: 1rem; }
        td { padding: .625rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        td small { display: block; color: var(--muted); font-size: .8125rem; }
        tr.this td { color: var(--navy); font-weight: 700; }
        tr.left td { font-weight: 600; border-bottom: 0; }
        .received { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; padding: 1.25rem 1.5rem; background: var(--paper); border-radius: .75rem; }
        .received p { margin: 0; color: var(--muted); }
        .received .amount { color: var(--navy); font-size: 2.25rem; font-weight: 800; line-height: 1.1; }
        .stamp { padding: .375rem .875rem; color: var(--crimson); font-weight: 800; letter-spacing: .08em; border: 2px solid var(--crimson); border-radius: .5rem; transform: rotate(-4deg); }
        .online { margin-top: 1rem; color: var(--muted); font-size: .875rem; }
        .foot { margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--line); color: var(--muted); font-size: .8125rem; }
        .print { margin-top: 1.5rem; font: inherit; font-weight: 600; color: #fff; background: var(--navy); border: 0; border-radius: .5rem; padding: .625rem 1.125rem; cursor: pointer; }
        .print:focus-visible { outline: 3px solid var(--crimson); outline-offset: 2px; }
        @media (max-width: 34rem) {
            .sheet { margin: 0; border: 0; }
            .band, .body { padding-left: 1.25rem; padding-right: 1.25rem; }
            .band span { display: none; }
            .parties { grid-template-columns: 1fr; gap: 1rem; }
            .date { text-align: left; width: 100%; }
        }
        @page { size: A4; margin: 14mm; }
        @media print {
            body { background: #fff; font-size: 12pt; }
            .sheet { max-width: none; margin: 0; border: 0; }
            .print { display: none; }
            .band, .received, .stamp { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <main class="sheet">
        <div class="band">
            <img src="{{ asset('images/oasis-logo-white.png') }}" width="150" height="56" alt="{{ config('app.name') }}">
            <span>{{ $site }}</span>
        </div>
        <div class="body">
            <div class="title">
                <div>
                    <h1>Receipt</h1>
                    <p class="number">{{ $payment->receipt_number }}</p>
                </div>
                <p class="date">Issued {{ $payment->created_at->format('j F Y') }}</p>
            </div>

            <div class="parties">
                <div>
                    <h2>Received from</h2>
                    <p><strong>{{ $user->name }}</strong></p>
                    @if ($membership?->number)<p>{{ $membership->number }}</p>@endif
                    <p>{{ $user->email }}</p>
                </div>
                <div>
                    <h2>Issued by</h2>
                    <p><strong>{{ config('app.name') }}</strong></p>
                    <p>{{ $site }}</p>
                    <p>{{ config('oasis.support_email') }}</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr><th scope="col">For</th><th scope="col">Amount</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $charge->label() }}</td>
                        <td>{{ $naira($charge->amount) }}</td>
                    </tr>
                    @if ($charge->waived_amount > 0)
                        <tr>
                            <td>Waived<small>{{ $charge->waiverDescription() }}</small></td>
                            <td>−{{ $naira($charge->waived_amount) }}</td>
                        </tr>
                    @endif
                    @foreach ($earlier as $other)
                        <tr>
                            <td>Earlier payment, {{ $other->paid_on->format('j M Y') }}<small>{{ $other->receipt_number }}</small></td>
                            <td>−{{ $naira($other->amount) }}</td>
                        </tr>
                    @endforeach
                    <tr class="this">
                        <td>This payment, {{ $payment->paid_on->format('j F Y') }}<small>{{ $payment->method->label() }}@if ($payment->reference), reference {{ $payment->reference }}@endif</small></td>
                        <td>−{{ $naira($payment->amount) }}</td>
                    </tr>
                    <tr class="left">
                        <td>
                            @if ($left === 0)
                                Left on this charge: nothing, it is settled
                            @else
                                Left on this charge
                                @if ($notDueYet)
                                    <small>Due {{ $charge->due_on->format('j F Y') }}</small>
                                @endif
                            @endif
                        </td>
                        <td>{{ $left === 0 ? '₦0' : $naira($left) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="received">
                <div>
                    <p>Amount received</p>
                    <p class="amount">{{ $naira($payment->amount) }}</p>
                </div>
                <span class="stamp" aria-hidden="true">PAID</span>
            </div>

            @if ($online)
                <p class="online">Paid online with Paystack: {{ $online->money($online->total_minor) }} charged, including Paystack's charge of {{ $online->money($online->fee_minor) }}@if ($online->currency === 'USD'), at ₦{{ number_format((float) $online->naira_per_dollar, 2) }} to the dollar plus {{ config('membership.dollar_margin_percent') }}%@endif.</p>
            @endif

            <p class="foot">Keep this receipt for your records. Questions about it: {{ config('oasis.support_email') }}, quoting {{ $payment->receipt_number }}.</p>
            <button type="button" class="print" onclick="window.print()">Print or save as PDF</button>
        </div>
    </main>
</body>
</html>
