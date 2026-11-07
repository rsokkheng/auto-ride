@php
    use App\Services\PartnerStatementService as PS;
    $k = fn ($n) => number_format((int) $n);
    $total = ['delivered' => $rows->sum('delivered'), 'cod' => $rows->sum('cod_collected'), 'fees' => $rows->sum('cash_fees'),
              'cash' => $rows->sum('cash_to_hand_over'), 'returning' => $rows->sum('returning'), 'returned' => $rows->sum('returned')];
@endphp
<!DOCTYPE html>
<html lang="km">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Driver cash {{ $date }}</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&family=Inter:wght@400;600;700&display=swap">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Kantumruy Pro','Inter',sans-serif; color:#111827; background:#f3f4f6; font-size:11px; }
.toolbar { position:sticky; top:0; background:#fff; border-bottom:1px solid #e5e7eb; padding:10px 16px; display:flex; gap:8px; align-items:center; }
.toolbar .sp { flex:1; }
.btn { border:1px solid #e5e7eb; background:#fff; padding:8px 14px; border-radius:8px; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; color:#111827; }
.btn-primary { background:#00B14F; border-color:#00B14F; color:#fff; }
.page { background:#fff; width:210mm; margin:16px auto; padding:12mm; box-shadow:0 2px 12px rgba(0,0,0,.08); }
.head { display:flex; justify-content:space-between; border-bottom:3px solid #00B14F; padding-bottom:8px; margin-bottom:10px; }
.brand { font-size:20px; font-weight:700; color:#00B14F; } .title { font-size:15px; font-weight:700; }
.en { color:#6b7280; font-size:10px; font-family:'Inter',sans-serif; }
.cards { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; margin-bottom:10px; }
.card { border:1px solid #e5e7eb; border-radius:6px; padding:7px 9px; } .card .v { font-size:15px; font-weight:700; } .card .l { font-size:9.5px; color:#6b7280; }
.card.hl { border-color:#00B14F; background:#ecfdf3; }
.driver { border:1px solid #e5e7eb; border-radius:8px; margin-bottom:10px; page-break-inside:avoid; }
.dh { display:flex; justify-content:space-between; align-items:center; background:#f9fafb; padding:8px 10px; border-bottom:1px solid #e5e7eb; border-radius:8px 8px 0 0; }
.dh .cash { font-size:15px; font-weight:700; color:#047857; }
table { width:100%; border-collapse:collapse; } th { text-align:left; font-size:9px; color:#6b7280; padding:4px 8px; border-bottom:1px solid #e5e7eb; }
td { padding:4px 8px; font-size:10px; border-bottom:1px solid #f3f4f6; } .r { text-align:right; }
.tag { padding:1px 5px; border-radius:3px; font-size:9px; font-weight:600; }
.t-ok { background:#d1fae5; color:#065f46; } .t-bad { background:#fee2e2; color:#991b1b; } .t-ret { background:#fef3c7; color:#92400e; }
.sign { display:grid; grid-template-columns:1fr 1fr; gap:30px; padding:16px 10px 6px; font-size:10px; text-align:center; }
.sign div { border-top:1px solid #9ca3af; padding-top:4px; }
@media print { body { background:#fff; } .toolbar { display:none; } .page { margin:0; box-shadow:none; width:auto; padding:0; } @page { size:A4; margin:10mm; } }
@media (max-width: 820px) { .page { width:auto; margin:8px; } .cards { grid-template-columns:repeat(2,1fr); } }
</style>
</head>
<body>
<div class="toolbar">
    <form method="GET" style="display:flex;gap:6px;align-items:center;">
        <input type="date" name="date" value="{{ $date }}" max="{{ now(PS::tz())->toDateString() }}" class="btn" style="padding:6px 8px;" onchange="this.form.submit()">
    </form>
    <button class="btn btn-primary" onclick="window.print()">🖨 បោះពុម្ព / PDF · Print</button>
    <span class="sp"></span>
    <a class="btn" href="{{ $backUrl }}">← ត្រឡប់ · Back</a>
</div>
<div class="page">
    <div class="head">
        <div>
            <div class="brand">ROTEH</div>
            <div class="title">ប្រាក់ដែលអ្នកបើកបរត្រូវប្រគល់ — កញ្ចប់ដៃគូ</div>
            <div class="en">Staff driver cash hand-over — Partner packages</div>
        </div>
        <div style="text-align:right;">ថ្ងៃបិទបញ្ជី · Closing date: <b>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</b><br>
            <span class="en">Generated {{ now(PS::tz())->format('d/m/Y H:i') }}</span></div>
    </div>
    <div class="cards">
        <div class="card"><div class="v">{{ $rows->count() }}</div><div class="l">អ្នកបើកបរ · Staff drivers</div></div>
        <div class="card"><div class="v">{{ $total['delivered'] }}</div><div class="l">បានដឹកដល់ · Delivered</div></div>
        <div class="card"><div class="v">{{ $total['returning'] + $total['returned'] }}</div><div class="l">មិនបានដឹកដល់ · Not delivered</div></div>
        <div class="card hl"><div class="v">{{ $k($total['cash']) }} ៛</div><div class="l">សាច់ប្រាក់ត្រូវប្រគល់ · Cash to hand over</div></div>
    </div>
    <p class="en" style="margin-bottom:10px;">អ្នកបើកបរជាបុគ្គលិក ROTEH ទទួលប្រាក់ខែ — សាច់ប្រាក់ដែលប្រមូលបានទាំងអស់ (COD + ថ្លៃដឹកដែលអ្នកទទួលបង់) ត្រូវប្រគល់ឲ្យ ROTEH។
        · Staff drivers are on a salary — all cash collected (COD + delivery fees paid by receivers) is handed to ROTEH.</p>

    @forelse($rows as $r)
        <div class="driver">
            <div class="dh">
                <div><b>{{ $r['driver']?->name }}</b> <span class="en">{{ $r['driver']?->phone }} @if($r['driver']?->driver_type !== 'employee')· ⚠ not staff @endif</span><br>
                    <span class="en">{{ $r['delivered'] }} delivered · {{ $r['returning'] }} returning · {{ $r['returned'] }} returned to shop</span></div>
                <div style="text-align:right;"><span class="en">COD {{ $k($r['cod_collected']) }} + fees {{ $k($r['cash_fees']) }}</span><br>
                    <span class="cash">{{ $k($r['cash_to_hand_over']) }} ៛</span></div>
            </div>
            <table>
                <thead><tr><th>Code</th><th>Partner</th><th>Receiver</th><th>Status</th><th>Time</th><th class="r">COD</th><th class="r">Fee (cash)</th></tr></thead>
                <tbody>
                @foreach($r['packages'] as $p)
                    <tr>
                        <td><b>{{ $p['code'] }}</b></td>
                        <td>{{ $p['partner'] }}</td>
                        <td>{{ $p['receiver'] }}</td>
                        <td><span class="tag {{ $p['status'] === 'completed' ? 't-ok' : ($p['status'] === 'returned' ? 't-ret' : 't-bad') }}">{{ PS::STATUS_LABELS[$p['status']][1] ?? $p['status'] }}</span>
                            @if($p['reason'] && $p['status'] !== 'completed')<span class="en">{{ PS::REASONS[$p['reason']][1] ?? '' }}</span>@endif</td>
                        <td>{{ $p['time'] }}</td>
                        <td class="r">{{ $p['cod'] ? $k($p['cod']) : '—' }}</td>
                        <td class="r">{{ $p['cash_fee'] ? $k($p['cash_fee']) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="sign"><div>អ្នកបើកបរ (អ្នកប្រគល់) · Driver</div><div>ROTEH (អ្នកទទួល) · Received by</div></div>
        </div>
    @empty
        <p style="text-align:center;color:#6b7280;padding:24px;">គ្មានកញ្ចប់ដៃគូដែលបានបិទនៅថ្ងៃនេះ · No partner packages closed on this day</p>
    @endforelse
</div>
@if(!empty($autoPrint))<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>@endif
</body>
</html>
