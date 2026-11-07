@php
    // dompdf can't shape Khmer script — show Latin text only, fall back otherwise.
    $latin = fn ($s) => $s !== null && $s !== '' && ! preg_match('/[\x{1780}-\x{17FF}\x{19E0}-\x{19FF}]/u', $s);
    $t = $report['totals']; $m = $report['money'];
    $periodLabel = ['day' => 'Daily report', 'week' => 'Weekly report', 'month' => 'Monthly report'][$report['period']];
    $range = $report['from'] === $report['to']
        ? \Carbon\Carbon::parse($report['from'])->format('d M Y')
        : \Carbon\Carbon::parse($report['from'])->format('d M Y') . ' – ' . \Carbon\Carbon::parse($report['to'])->format('d M Y');
    $statusLabels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'driver_assigned' => 'Driver assigned', 'picked_up' => 'Picked up',
        'in_transit' => 'In transit', 'completed' => 'Completed', 'failed' => 'Not delivered', 'returned' => 'Returned to shop', 'cancelled' => 'Cancelled'];
    $statusTone = ['completed' => 'success', 'failed' => 'danger', 'returned' => 'warning', 'cancelled' => 'secondary'];
    $reasonLabels = ['cannot_contact' => 'Cannot contact receiver', 'receiver_refused' => 'Receiver refused', 'wrong_address' => 'Wrong address',
        'receiver_postponed' => 'Receiver asked to deliver later', 'other' => 'Other'];
    $maxBar = max(1, collect($report['series'])->max('total'));
    $notDelivered = $t['failed'] + $t['returned'];
    $tz = config('ride.local_timezone', 'Asia/Phnom_Penh');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size:10px; color:#1f2937; }
.header { background:#00B14F; color:#fff; padding:14px 16px; }
.header h1 { font-size:17px; }
.header .sub { font-size:10px; margin-top:3px; }
.wrap { padding:14px 16px; }
.cards { width:100%; border-collapse:separate; border-spacing:6px; margin:0 -6px 6px; }
.cards td { border:1px solid #e5e7eb; border-radius:6px; padding:9px; text-align:center; width:25%; }
.cards .v { font-size:17px; font-weight:bold; color:#111827; }
.cards .l { font-size:8.5px; color:#6b7280; margin-top:2px; }
h2 { font-size:11.5px; color:#00873c; border-left:4px solid #00B14F; padding-left:7px; margin:12px 0 7px; }
table.data { width:100%; border-collapse:collapse; }
table.data th { background:#374151; color:#fff; font-size:8.5px; text-align:left; padding:5px 6px; }
table.data td { font-size:8.5px; padding:4px 6px; border-bottom:1px solid #eee; }
table.data tr:nth-child(even) td { background:#f9fafb; }
.r { text-align:right; }
.badge { padding:1px 5px; border-radius:3px; font-size:7.5px; font-weight:bold; }
.b-success { background:#d1fae5; color:#065f46; } .b-danger { background:#fee2e2; color:#991b1b; }
.b-warning { background:#fef3c7; color:#92400e; } .b-secondary, .b-info { background:#eef2ff; color:#374151; }
.bars { width:100%; border-collapse:collapse; height:110px; }
.bars td { vertical-align:bottom; text-align:center; padding:0 1px; }
.bar-done { background:#00B14F; } .bar-fail { background:#d97706; } .bar-open { background:#93c5fd; } .bar-cancel { background:#d1d5db; }
.axis td { font-size:7px; color:#6b7280; text-align:center; }
.legend span { display:inline-block; width:8px; height:8px; margin:0 3px 0 10px; }
.money td { padding:4px 0; font-size:10px; } .money td.r { font-weight:bold; }
.footer { margin-top:16px; border-top:1px solid #e5e7eb; padding-top:6px; font-size:8px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header">
    <h1>ROTEH Partner — {{ $periodLabel }}</h1>
    <div class="sub">{{ $range }} @if($latin($business)) · {{ $business }} @endif</div>
</div>
<div class="wrap">

<table class="cards"><tr>
    <td><div class="v">{{ number_format($t['total']) }}</div><div class="l">Total packages</div></td>
    <td><div class="v" style="color:#00873c;">{{ number_format($t['completed']) }}</div><div class="l">Completed</div></td>
    <td><div class="v" style="color:#b45309;">{{ number_format($notDelivered) }}</div><div class="l">Not delivered / returned</div></td>
    <td><div class="v">{{ $report['success_rate'] === null ? '—' : $report['success_rate'] . '%' }}</div><div class="l">Success rate</div></td>
</tr><tr>
    <td><div class="v">{{ number_format($t['in_progress']) }}</div><div class="l">In progress</div></td>
    <td><div class="v">{{ number_format($t['cancelled']) }}</div><div class="l">Cancelled</div></td>
    <td><div class="v">{{ number_format($m['delivery_fees']) }} KHR</div><div class="l">Delivery fees</div></td>
    <td><div class="v">{{ $report['avg_delivery_minutes'] === null ? '—' : $report['avg_delivery_minutes'] . ' min' }}</div><div class="l">Avg. delivery time</div></td>
</tr></table>

<h2>Money</h2>
<table class="money" style="width:60%;">
    <tr><td>Delivery fees (completed)</td><td class="r">{{ number_format($m['delivery_fees']) }} KHR</td></tr>
    <tr><td>COD collected from receivers</td><td class="r">{{ number_format($m['cod_collected']) }} KHR</td></tr>
    <tr><td>COD still to collect</td><td class="r">{{ number_format($m['cod_pending']) }} KHR</td></tr>
</table>

<h2>{{ $report['period'] === 'day' ? 'Packages by hour' : 'Packages by day' }}</h2>
<div class="legend" style="font-size:8px;margin-bottom:4px;">
    <span class="bar-done"></span>Completed <span class="bar-fail"></span>Not delivered / returned <span class="bar-open"></span>In progress <span class="bar-cancel"></span>Cancelled
</div>
<table class="bars"><tr>
@foreach($report['series'] as $b)
    @php
        $open = max(0, $b['total'] - $b['completed'] - $b['not_delivered'] - $b['cancelled']);
        $px = fn ($n) => (int) round($n / $maxBar * 100);
    @endphp
    <td>
        @if($open)<div class="bar-open" style="height:{{ $px($open) }}px;"></div>@endif
        @if($b['not_delivered'])<div class="bar-fail" style="height:{{ $px($b['not_delivered']) }}px;"></div>@endif
        @if($b['completed'])<div class="bar-done" style="height:{{ $px($b['completed']) }}px;"></div>@endif
        @if($b['cancelled'])<div class="bar-cancel" style="height:{{ $px($b['cancelled']) }}px;"></div>@endif
    </td>
@endforeach
</tr></table>
<table class="bars axis" style="height:auto;"><tr>
@foreach($report['series'] as $i => $b)
    <td>
        @if($report['period'] === 'day'){{ $i % 3 === 0 ? $b['key'] : '' }}
        @elseif($report['period'] === 'week'){{ \Carbon\Carbon::parse($b['key'])->format('D d') }}
        @else{{ in_array(\Carbon\Carbon::parse($b['key'])->day, [1, 5, 10, 15, 20, 25, 30]) ? \Carbon\Carbon::parse($b['key'])->day : '' }}@endif
    </td>
@endforeach
</tr></table>

@if($notDelivered > 0)
<h2>Why packages were not delivered</h2>
<table class="data" style="width:60%;">
    <thead><tr><th>Reason</th><th class="r">Packages</th></tr></thead>
    <tbody>
    @foreach($report['failure_reasons'] as $k => $n) @if($n > 0)
        <tr><td>{{ $reasonLabels[$k] ?? $k }}</td><td class="r">{{ $n }}</td></tr>
    @endif @endforeach
    </tbody>
</table>
@endif

<h2>Packages ({{ number_format($rows->count() + ($hiddenRows ?? 0)) }})</h2>
@if(($hiddenRows ?? 0) > 0)
    <p style="font-size:8.5px;color:#92400e;margin-bottom:6px;">Showing the first {{ number_format($rows->count()) }} packages — export to Excel for all {{ number_format($rows->count() + $hiddenRows) }}.</p>
@endif
<table class="data">
    <thead><tr><th>Code</th><th>Created</th><th>Status</th><th>Reference</th><th>Receiver phone</th><th>Size</th><th class="r">Fee (KHR)</th><th class="r">COD (KHR)</th><th>Pays</th></tr></thead>
    <tbody>
    @forelse($rows as $d)
        @php($ps = $d->packageStatus())
        <tr>
            <td>PKG-{{ str_pad($d->id, 6, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $d->created_at?->copy()->setTimezone($tz)->format('d/m H:i') }}</td>
            <td>
                <span class="badge b-{{ $statusTone[$ps] ?? 'info' }}">{{ $statusLabels[$ps] ?? $ps }}</span>
                @if(in_array($ps, ['failed', 'returned']) && $d->failure_reason)<br><span style="color:#991b1b;font-size:7.5px;">{{ $reasonLabels[$d->failure_reason] ?? '' }}</span>@endif
            </td>
            <td>{{ $latin($d->partner_reference) ? $d->partner_reference : '' }}</td>
            <td>{{ $d->recipient_phone }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $d->package_size ?? '')) }}{{ $d->service_option === 'express' ? ' · Express' : '' }}</td>
            <td class="r">{{ number_format($d->fee) }}</td>
            <td class="r">{{ $d->package_amount ? number_format($d->package_amount) : '' }}</td>
            <td>{{ $d->payment_by === 'recipient' ? 'Receiver' : 'Partner' }}</td>
        </tr>
    @empty
        <tr><td colspan="9" style="text-align:center;color:#6b7280;padding:12px;">No packages in this period</td></tr>
    @endforelse
    </tbody>
</table>

<div class="footer">Generated {{ $generatedAt->format('d M Y H:i') }} (Phnom Penh time) · ROTEH · Full details with receiver names and addresses are in the Excel export.</div>
</div>
</body>
</html>
