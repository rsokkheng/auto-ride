<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settlement Receipt #{{ $settlement->id }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 13px; color: #1a202c; background: #f7f8fa; }

.receipt-wrapper { max-width: 760px; margin: 24px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 16px rgba(0,0,0,.10); overflow: hidden; }

/* Header */
.receipt-header { background: linear-gradient(135deg, #1e3a5f 0%, #2d5986 100%); color: #fff; padding: 28px 32px 22px; display: flex; justify-content: space-between; align-items: flex-start; }
.receipt-header .company h2 { font-size: 1.5rem; font-weight: 700; letter-spacing: .5px; }
.receipt-header .company p { font-size: .8rem; opacity: .8; margin-top: 4px; }
.receipt-header .ref { text-align: right; }
.receipt-header .ref .ref-num { font-size: 1.1rem; font-weight: 700; background: rgba(255,255,255,.18); padding: 6px 14px; border-radius: 20px; display: inline-block; }
.receipt-header .ref .ref-label { font-size: .75rem; opacity: .7; margin-top: 4px; }

/* Status strip */
.status-strip { padding: 8px 32px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #e2e8f0; }
.badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.badge-draft       { background: #e2e8f0; color: #64748b; }
.badge-pending     { background: #fef3c7; color: #92400e; }
.badge-approved    { background: #dbeafe; color: #1e40af; }
.badge-processing  { background: #e0e7ff; color: #3730a3; }
.badge-paid        { background: #d1fae5; color: #065f46; }
.badge-failed      { background: #fee2e2; color: #991b1b; }
.badge-cancelled   { background: #f1f5f9; color: #475569; }
.status-strip .date { font-size: .8rem; color: #64748b; margin-left: auto; }

/* Body */
.receipt-body { padding: 24px 32px; }

/* Info grid */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
.info-block { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; }
.info-block h4 { font-size: .7rem; text-transform: uppercase; letter-spacing: .8px; color: #64748b; margin-bottom: 8px; }
.info-block p { font-size: .9rem; color: #1a202c; margin-bottom: 3px; }
.info-block p.primary { font-size: 1rem; font-weight: 700; color: #1e3a5f; }
.info-block p.muted   { color: #64748b; font-size: .8rem; }

/* Financial table */
.fin-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.fin-table th { background: #1e3a5f; color: #fff; padding: 9px 14px; text-align: left; font-size: .8rem; letter-spacing: .3px; }
.fin-table td { padding: 9px 14px; border-bottom: 1px solid #f1f5f9; font-size: .88rem; }
.fin-table tr:last-child td { border-bottom: none; }
.fin-table tr.subtotal td { background: #f8fafc; font-weight: 600; }
.fin-table tr.total td { background: #1e3a5f; color: #fff; font-weight: 700; font-size: 1rem; border-bottom: none; }
.fin-table td.label { color: #374151; }
.fin-table td.amount { text-align: right; white-space: nowrap; }
.fin-table td.debit  { text-align: right; color: #dc2626; }
.fin-table td.credit { text-align: right; color: #16a34a; }

/* Line items */
.items-table { width: 100%; border-collapse: collapse; font-size: .82rem; margin-bottom: 20px; }
.items-table th { background: #374151; color: #fff; padding: 7px 12px; text-align: left; font-size: .78rem; }
.items-table td { padding: 7px 12px; border-bottom: 1px solid #f1f5f9; }
.items-table tr:nth-child(even) td { background: #f9fafb; }
.items-table td.r { text-align: right; }

/* Payment box */
.payment-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; margin-bottom: 24px; background: #f8fafc; }
.payment-box h4 { font-size: .7rem; text-transform: uppercase; letter-spacing: .8px; color: #64748b; margin-bottom: 10px; }
.payment-box .row { display: flex; gap: 32px; flex-wrap: wrap; }
.payment-box .col label { font-size: .7rem; color: #94a3b8; display: block; margin-bottom: 2px; }
.payment-box .col span  { font-size: .88rem; color: #1a202c; font-weight: 600; }

/* Signature */
.sig-row { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 20px; }
.sig-box { border-top: 2px solid #e2e8f0; padding-top: 10px; text-align: center; }
.sig-box .sig-line { height: 50px; }
.sig-box p { font-size: .75rem; color: #64748b; }

/* Footer */
.receipt-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 32px; text-align: center; font-size: .75rem; color: #94a3b8; }

/* Print actions (hidden on print) */
.print-actions { text-align: center; padding: 20px; background: #f7f8fa; border-top: 1px solid #e2e8f0; }
.btn-print { background: #1e3a5f; color: #fff; border: none; padding: 10px 28px; border-radius: 6px; font-size: .9rem; cursor: pointer; margin: 0 6px; }
.btn-back  { background: #e2e8f0; color: #374151; border: none; padding: 10px 28px; border-radius: 6px; font-size: .9rem; cursor: pointer; margin: 0 6px; text-decoration: none; display: inline-block; }

@media print {
    body { background: #fff; }
    .receipt-wrapper { box-shadow: none; margin: 0; border-radius: 0; max-width: 100%; }
    .print-actions { display: none; }
    .receipt-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .status-strip, .badge, .fin-table th, .fin-table tr.total td, .items-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>
<div class="receipt-wrapper">

    {{-- Header --}}
    <div class="receipt-header">
        <div class="company">
            <h2>Auto Ride</h2>
            <p>Payment Settlement Receipt</p>
        </div>
        <div class="ref">
            <div class="ref-num">STLMT-{{ str_pad($settlement->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div class="ref-label">Settlement Reference</div>
        </div>
    </div>

    {{-- Status --}}
    <div class="status-strip">
        <span class="badge badge-{{ $settlement->status }}">{{ ucfirst($settlement->status) }}</span>
        @if($settlement->settlement_type === 'driver')
        <span style="font-size:.8rem;color:#64748b;"><i>Driver Settlement</i></span>
        @else
        <span style="font-size:.8rem;color:#64748b;"><i>Partner Settlement</i></span>
        @endif
        <span class="date">Generated: {{ now()->format('d M Y, H:i') }}</span>
    </div>

    <div class="receipt-body">

        {{-- Info Grid --}}
        <div class="info-grid">
            <div class="info-block">
                <h4>{{ $settlement->settlement_type === 'driver' ? 'Driver' : 'Partner' }} Details</h4>
                <p class="primary">{{ $settlement->user->name ?? '—' }}</p>
                <p>{{ $settlement->user->phone ?? '—' }}</p>
                @if($settlement->account_holder)
                <p class="muted">Account: {{ $settlement->account_holder }}</p>
                @endif
            </div>
            <div class="info-block">
                <h4>Settlement Period</h4>
                <p class="primary">{{ $settlement->period_start->format('d M Y') }} – {{ $settlement->period_end->format('d M Y') }}</p>
                <p class="muted">Created: {{ $settlement->created_at->format('d M Y') }} by {{ $settlement->creator->name ?? 'System' }}</p>
                @if($settlement->paid_at)
                <p class="muted" style="color:#16a34a;">Paid: {{ $settlement->paid_at->format('d M Y H:i') }}</p>
                @endif
            </div>
        </div>

        {{-- Financial Summary --}}
        @if($settlement->settlement_type === 'driver')
        <table class="fin-table">
            <thead><tr><th>Description</th><th class="amount">Amount (KHR)</th></tr></thead>
            <tbody>
                <tr><td class="label">Ride Earnings</td><td class="credit">+ {{ number_format($settlement->gross_earnings - ($settlement->gross_earnings > 0 && $settlement->deliveries_count > 0 ? 0 : 0)) }}</td></tr>
                <tr><td class="label">Total Gross Earnings ({{ $settlement->rides_count + $settlement->deliveries_count }} trips)</td><td class="credit">+ {{ number_format($settlement->gross_earnings) }} ៛</td></tr>
                <tr class="subtotal"><td class="label">Less: Platform Commission</td><td class="debit">- {{ number_format($settlement->commission_total) }} ៛</td></tr>
                @if($settlement->tips_total > 0)
                <tr class="subtotal"><td class="label">Add: Tips Received</td><td class="credit">+ {{ number_format($settlement->tips_total) }} ៛</td></tr>
                @endif
                @if($settlement->cod_collected > 0)
                <tr class="subtotal"><td class="label">Less: COD Cash to Remit</td><td class="debit">- {{ number_format($settlement->cod_collected) }} ៛</td></tr>
                @endif
                @if($settlement->adjustments != 0)
                <tr class="subtotal"><td class="label">Adjustment @if($settlement->adjustment_note)<small>({{ $settlement->adjustment_note }})</small>@endif</td><td class="{{ $settlement->adjustments >= 0 ? 'credit' : 'debit' }}">{{ $settlement->adjustments >= 0 ? '+' : '-' }} {{ number_format(abs($settlement->adjustments)) }} ៛</td></tr>
                @endif
                <tr class="total">
                    <td>NET PAYOUT TO DRIVER</td>
                    <td class="amount">{{ number_format(abs($settlement->net_payout)) }} ៛{{ $settlement->net_payout < 0 ? ' (OWED)' : '' }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Driver stats --}}
        <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:100px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px;text-align:center;">
                <div style="font-size:1.4rem;font-weight:700;color:#0369a1;">{{ $settlement->rides_count }}</div>
                <div style="font-size:.75rem;color:#0284c7;">Rides</div>
            </div>
            <div style="flex:1;min-width:100px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:12px;text-align:center;">
                <div style="font-size:1.4rem;font-weight:700;color:#15803d;">{{ $settlement->deliveries_count }}</div>
                <div style="font-size:.75rem;color:#16a34a;">Deliveries</div>
            </div>
            <div style="flex:1;min-width:100px;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:12px;text-align:center;">
                <div style="font-size:1.4rem;font-weight:700;color:#c2410c;">{{ number_format($settlement->commission_total) }}</div>
                <div style="font-size:.75rem;color:#ea580c;">Commission ៛</div>
            </div>
        </div>

        @else
        {{-- Partner financial --}}
        <table class="fin-table">
            <thead><tr><th>Description</th><th class="amount">Amount (KHR)</th></tr></thead>
            <tbody>
                <tr><td class="label">COD Collected by Drivers for Partner ({{ $settlement->orders_count }} orders)</td><td class="credit">+ {{ number_format($settlement->cod_handled) }} ៛</td></tr>
                <tr class="subtotal"><td class="label">Less: Delivery Fees Charged</td><td class="debit">- {{ number_format($settlement->delivery_fees) }} ៛</td></tr>
                @if($settlement->adjustments != 0)
                <tr class="subtotal"><td class="label">Adjustment @if($settlement->adjustment_note)<small>({{ $settlement->adjustment_note }})</small>@endif</td><td class="{{ $settlement->adjustments >= 0 ? 'credit' : 'debit' }}">{{ $settlement->adjustments >= 0 ? '+' : '-' }} {{ number_format(abs($settlement->adjustments)) }} ៛</td></tr>
                @endif
                <tr class="total">
                    <td>NET SETTLEMENT</td>
                    <td class="amount">{{ number_format(abs($settlement->net_payout)) }} ៛{{ $settlement->net_payout < 0 ? ' (PARTNER OWES)' : '' }}</td>
                </tr>
            </tbody>
        </table>
        @endif

        {{-- Payment Info --}}
        @if($settlement->payment_method || $settlement->bank_name || $settlement->bank_account)
        <div class="payment-box">
            <h4>Payment Details</h4>
            <div class="row">
                @if($settlement->payment_method)
                <div class="col"><label>Method</label><span>{{ ucwords(str_replace('_', ' ', $settlement->payment_method)) }}</span></div>
                @endif
                @if($settlement->bank_name)
                <div class="col"><label>Bank</label><span>{{ $settlement->bank_name }}</span></div>
                @endif
                @if($settlement->bank_account)
                <div class="col"><label>Account No.</label><span>{{ $settlement->bank_account }}</span></div>
                @endif
                @if($settlement->payment_reference)
                <div class="col"><label>Reference</label><span>{{ $settlement->payment_reference }}</span></div>
                @endif
            </div>
        </div>
        @endif

        {{-- Line Items --}}
        @if(count($lineItems) > 0)
        <h4 style="font-size:.8rem;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:8px;">Transaction Details</h4>
        <table class="items-table">
            <thead>
                <tr><th>Date</th><th>Type</th><th>Description</th><th class="r">Credit</th><th class="r">Debit</th></tr>
            </thead>
            <tbody>
                @foreach(array_slice($lineItems, 0, 30) as $item)
                @php
                    $typeLabel = match($item['type']) {
                        'trip_earning'        => 'Trip Earning',
                        'delivery_payment'    => 'Delivery Pay',
                        'platform_commission' => 'Commission',
                        'tip_in'              => 'Tip',
                        'cod_collected'       => 'COD',
                        'cod_received'        => 'COD (Partner)',
                        'delivery_fee'        => 'Delivery Fee',
                        default               => ucwords(str_replace('_', ' ', $item['type']))
                    };
                @endphp
                <tr>
                    <td>{{ is_string($item['date']) ? \Carbon\Carbon::parse($item['date'])->format('d M H:i') : \Carbon\Carbon::instance($item['date'])->format('d M H:i') }}</td>
                    <td>{{ $typeLabel }}</td>
                    <td style="color:#64748b;">{{ Str::limit($item['note'] ?? '', 40) }}</td>
                    <td class="r" style="color:#16a34a;">{{ $item['credit'] > 0 ? number_format($item['credit']) : '' }}</td>
                    <td class="r" style="color:#dc2626;">{{ $item['debit'] > 0 ? number_format($item['debit']) : '' }}</td>
                </tr>
                @endforeach
                @if(count($lineItems) > 30)
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;font-style:italic;">... and {{ count($lineItems) - 30 }} more transactions</td></tr>
                @endif
            </tbody>
        </table>
        @endif

        {{-- Notes --}}
        @if($settlement->notes)
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:12px 16px;margin-bottom:20px;">
            <strong style="font-size:.75rem;color:#92400e;">Notes:</strong>
            <p style="font-size:.85rem;color:#78350f;margin-top:4px;white-space:pre-line;">{{ $settlement->notes }}</p>
        </div>
        @endif

        {{-- Signatures --}}
        <div class="sig-row">
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>Authorised Signature</p>
                <p style="font-weight:600;margin-top:2px;">Auto Ride Co.</p>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>Received By</p>
                <p style="font-weight:600;margin-top:2px;">{{ $settlement->user->name ?? '—' }}</p>
            </div>
        </div>

    </div>{{-- /receipt-body --}}

    <div class="receipt-footer">
        Auto Ride Platform &nbsp;·&nbsp; Settlement STLMT-{{ str_pad($settlement->id, 6, '0', STR_PAD_LEFT) }} &nbsp;·&nbsp; Generated {{ now()->format('d M Y H:i') }}
    </div>

    <div class="print-actions">
        <button class="btn-print" onclick="window.print()">
            🖨 Print Receipt
        </button>
        <a href="{{ route('admin.settlements.receipt.pdf', $settlement) }}" class="btn-print" style="background:#16a34a;">
            ⬇ Download PDF
        </a>
        <a href="{{ route('admin.settlements.show', $settlement) }}" class="btn-back">
            ← Back to Settlement
        </a>
    </div>

</div>
</body>
</html>
