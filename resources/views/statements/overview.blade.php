@php
    use App\Services\PartnerStatementService as PS;
    $L = PS::STATUS_LABELS;
    $k = fn ($n) => number_format((int) $n);
    $statusFilter = [
        'pending' => 'Has pending', 'open' => 'Has open', 'successful' => 'Has successful',
        'unsuccessful' => 'Has unsuccessful', 'returned' => 'Has not delivered / returned', 'owes' => 'Partner owes ROTEH',
    ][$filters['status'] ?? ''] ?? null;
@endphp
<!DOCTYPE html>
<html lang="km">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Partner Statements {{ $date }}</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&family=Inter:wght@400;600;700&display=swap">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Kantumruy Pro','Inter',sans-serif; color:#111827; background:#f3f4f6; font-size:11px; }
.toolbar { position:sticky; top:0; background:#fff; border-bottom:1px solid #e5e7eb; padding:10px 16px; display:flex; gap:8px; }
.toolbar .sp { flex:1; }
.btn { border:1px solid #e5e7eb; background:#fff; padding:8px 14px; border-radius:8px; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; color:#111827; }
.btn-primary { background:#00B14F; border-color:#00B14F; color:#fff; }
.page { background:#fff; width:297mm; margin:16px auto; padding:12mm; box-shadow:0 2px 12px rgba(0,0,0,.08); }
.head { display:flex; justify-content:space-between; border-bottom:3px solid #00B14F; padding-bottom:8px; margin-bottom:10px; }
.brand { font-size:20px; font-weight:700; color:#00B14F; }
.title { font-size:15px; font-weight:700; }
.en { color:#6b7280; font-size:10px; font-family:'Inter',sans-serif; }
.cards { display:grid; grid-template-columns:repeat(8,1fr); gap:6px; margin-bottom:10px; }
.card { border:1px solid #e5e7eb; border-radius:6px; padding:6px 8px; }
.card .v { font-size:14px; font-weight:700; } .card .l { font-size:9px; color:#6b7280; }
table { width:100%; border-collapse:collapse; }
th { background:#1f2937; color:#fff; font-size:9px; padding:5px 4px; text-align:left; }
th .en { color:#cbd5e1; display:block; font-size:8px; }
td { font-size:9.5px; padding:4px; border-bottom:1px solid #e5e7eb; }
tr:nth-child(even) td { background:#f9fafb; }
.r { text-align:right; white-space:nowrap; } .c { text-align:center; }
.zero { color:#d1d5db; }
tfoot td { font-weight:700; background:#ecfdf3 !important; border-top:2px solid #00B14F; }
@media print { body { background:#fff; } .toolbar { display:none; } .page { margin:0; box-shadow:none; width:auto; padding:0; } @page { size:A4 landscape; margin:8mm; } th, tfoot td { -webkit-print-color-adjust:exact; print-color-adjust:exact; } tr { page-break-inside:avoid; } }
@media (max-width: 1100px) { .page { width:auto; margin:8px; overflow-x:auto; } table { min-width:1000px; } .cards { grid-template-columns:repeat(4,1fr); } }
</style>
</head>
<body>
<div class="toolbar">
    <button class="btn btn-primary" onclick="window.print()">🖨 បោះពុម្ព / PDF · Print / Save PDF</button>
    <a class="btn" href="{{ $excelUrl }}">⬇ Excel</a>
    <span class="sp"></span>
    <a class="btn" href="{{ $backUrl }}">← ត្រឡប់ · Back</a>
</div>
<div class="page">
    <div class="head">
        <div>
            <div class="brand">ROTEH</div>
            <div class="title">របាយការណ៍ដៃគូទាំងអស់ — គ្រប់ស្ថានភាព</div>
            <div class="en">All Partners Statement — every status</div>
        </div>
        <div style="text-align:right;">
            ថ្ងៃបិទបញ្ជី · Closing date: <b>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</b><br>
            <span class="en">{{ $totals['partners'] }} partners
                @if(!empty($filters['search'])) · search “{{ $filters['search'] }}” @endif
                @if($statusFilter) · {{ $statusFilter }} @endif
                · generated {{ now(PS::tz())->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <div class="cards">
        <div class="card"><div class="v">{{ $k($totals['total']) }}</div><div class="l">សរុប · Total</div></div>
        @foreach($L as $g => $lbl)
            <div class="card"><div class="v">{{ $k($totals['counts'][$g]) }}</div><div class="l">{{ $lbl[1] }}</div></div>
        @endforeach
    </div>

    <table>
        <thead><tr>
            <th>#</th>
            <th>ដៃគូ<span class="en">Partner</span></th>
            <th class="r">សរុប<span class="en">Total</span></th>
            @foreach($L as $g => $lbl)<th class="c">{{ $lbl[1] }}<span class="en">{{ $lbl[0] }}</span></th>@endforeach
            <th class="r">សរុបបានប្រមូល<span class="en">Total collected</span></th>
            <th class="r">ថ្លៃដឹក ROTEH<span class="en">ROTEH fees</span></th>
            <th class="r">ROTEH បង់ដៃគូ<span class="en">ROTEH pays</span></th>
            <th class="r">ដៃគូបង់ ROTEH<span class="en">Partner pays</span></th>
            <th class="r">សមតុល្យ<span class="en">Balance</span></th>
        </tr></thead>
        <tbody>
        @forelse($rows as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><b>{{ $r['name'] }}</b><div class="en">{{ $r['phone'] }}</div></td>
                <td class="r"><b>{{ $r['total'] }}</b></td>
                @foreach($L as $g => $lbl)<td class="c {{ $r['counts'][$g] ? '' : 'zero' }}">{{ $r['counts'][$g] }}</td>@endforeach
                <td class="r">{{ $k($r['total_collected']) }}</td>
                <td class="r">{{ $k($r['service_fees']) }}</td>
                <td class="r" style="color:#047857;">{{ $k($r['roteh_pays_partner']) }}</td>
                <td class="r" style="color:#b91c1c;">{{ $k($r['partner_pays_roteh']) }}</td>
                <td class="r" style="{{ $r['net_to_partner'] < 0 ? 'color:#dc2626;' : 'color:#047857;' }}"><b>{{ $k($r['net_to_partner']) }}</b></td>
            </tr>
        @empty
            <tr><td colspan="{{ 8 + count($L) }}" style="text-align:center;padding:20px;color:#6b7280;">គ្មានទិន្នន័យ · No partners</td></tr>
        @endforelse
        </tbody>
        @if(count($rows))
        <tfoot><tr>
            <td colspan="2">សរុប · Total</td>
            <td class="r">{{ $k($totals['total']) }}</td>
            @foreach($L as $g => $lbl)<td class="c">{{ $k($totals['counts'][$g]) }}</td>@endforeach
            <td class="r">{{ $k($totals['collected']) }}</td>
            <td class="r">{{ $k($totals['fees']) }}</td>
            <td class="r">{{ $k($totals['roteh_pays']) }}</td>
            <td class="r">{{ $k($totals['partner_pays']) }}</td>
            <td class="r">{{ $k($totals['net']) }}</td>
        </tr></tfoot>
        @endif
    </table>
</div>
@if(!empty($autoPrint))<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>@endif
</body>
</html>
