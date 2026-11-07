@php
    use App\Services\PartnerStatementService as PS;
    $s = $statement; $sum = $s['summary'];
    $k = fn ($n) => number_format((int) $n);
    $dateLabel = $s['from'] === $s['to']
        ? \Carbon\Carbon::parse($s['from'])->format('d/m/Y')
        : \Carbon\Carbon::parse($s['from'])->format('d/m/Y') . ' – ' . \Carbon\Carbon::parse($s['to'])->format('d/m/Y');
    $sizes = ['small' => 'តូច', 'medium' => 'មធ្យម', 'large' => 'ធំ', 'extra_large' => 'ធំខ្លាំង'];
    $paidBy = ['receiver' => 'អ្នកទទួលបង់ · Receiver', 'partner_prepaid' => 'បង់រួច · Prepaid', 'partner_from_cod' => 'កាត់ពី COD · From COD'];
    $show = fn ($t) => $type === $t || ($type === 'both' && $t !== 'all');
    $groupTag = ['pending' => 't-can', 'confirmed' => 't-can', 'on_the_way' => 't-ret', 'completed' => 't-ok',
                 'failed' => 't-fail', 'returned' => 't-ret', 'cancelled' => 't-can'];
@endphp
<!DOCTYPE html>
<html lang="km">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $s['statement_no'] }} · {{ $s['partner']['business_name'] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap">
<style>
:root { --green:#00B14F; --ink:#111827; --muted:#6b7280; --line:#e5e7eb; --red:#dc2626; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Kantumruy Pro','Inter',sans-serif; color:var(--ink); background:#f3f4f6; font-size:12px; line-height:1.45; }
.toolbar { position:sticky; top:0; z-index:5; background:#fff; border-bottom:1px solid var(--line); padding:10px 16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.toolbar .sp { flex:1; }
.btn { border:1px solid var(--line); background:#fff; color:var(--ink); padding:8px 14px; border-radius:8px; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; gap:6px; align-items:center; }
.btn-primary { background:var(--green); border-color:var(--green); color:#fff; }
.page { background:#fff; width:210mm; min-height:297mm; margin:16px auto; padding:14mm 12mm; box-shadow:0 2px 12px rgba(0,0,0,.08); }
.head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid var(--green); padding-bottom:10px; margin-bottom:12px; }
.brand { font-size:22px; font-weight:700; color:var(--green); letter-spacing:.5px; }
.title { font-size:16px; font-weight:700; margin-top:2px; }
.title.fail { color:var(--red); }
.en { color:var(--muted); font-size:10.5px; font-family:'Inter',sans-serif; }
.meta { text-align:right; font-size:11px; }
.meta b { font-size:12px; }
.info { display:grid; grid-template-columns:1fr 1fr; gap:4px 16px; margin-bottom:12px; font-size:11.5px; }
.info div span { color:var(--muted); }
.cards { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:12px; }
.card { border:1px solid var(--line); border-radius:8px; padding:8px 10px; }
.card .v { font-size:16px; font-weight:700; }
.card .l { font-size:10.5px; color:var(--muted); }
.card.hl { border-color:var(--green); background:#ecfdf3; }
.card.neg { border-color:#fecaca; background:#fef2f2; }
table { width:100%; border-collapse:collapse; font-size:10.5px; }
th { background:#1f2937; color:#fff; font-weight:600; padding:6px 5px; text-align:left; vertical-align:bottom; }
th .en { color:#cbd5e1; font-size:9px; display:block; font-weight:400; }
td { padding:5px; border-bottom:1px solid var(--line); vertical-align:top; }
tr:nth-child(even) td { background:#f9fafb; }
.r { text-align:right; white-space:nowrap; }
.code { font-family:'Inter',sans-serif; font-weight:600; white-space:nowrap; }
.small { font-size:9.5px; color:var(--muted); }
tfoot td { font-weight:700; background:#ecfdf3 !important; border-top:2px solid var(--green); }
.tag { display:inline-block; padding:1px 6px; border-radius:4px; font-size:9.5px; font-weight:600; }
.t-ok { background:#d1fae5; color:#065f46; } .t-ret { background:#fef3c7; color:#92400e; } .t-fail { background:#fee2e2; color:#991b1b; } .t-can { background:#e5e7eb; color:#374151; }
.empty { text-align:center; color:var(--muted); padding:22px; }
.note { font-size:10px; color:var(--muted); margin-top:8px; }
.balance { display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding:10px 14px; border-radius:8px; background:#ecfdf3; border:1px solid var(--green); font-weight:600; }
.balance.owe { background:#fef2f2; border-color:#fca5a5; }
.balance .amt { font-size:18px; font-weight:700; }
.sign { display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:34px; font-size:11px; text-align:center; }
.sign div { border-top:1px solid #9ca3af; padding-top:6px; }
@media print {
  body { background:#fff; }
  .toolbar { display:none; }
  .page { margin:0; box-shadow:none; width:auto; min-height:auto; padding:0; page-break-after:always; }
  .page:last-child { page-break-after:auto; }
  @page { size:A4; margin:10mm; }
  th { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  tr, td { page-break-inside:avoid; }
}
@media (max-width: 820px) {
  .page { width:auto; min-height:auto; margin:8px; padding:12px; }
  .cards { grid-template-columns:repeat(2,1fr); }
  .info { grid-template-columns:1fr; }
  .tbl { overflow-x:auto; }
  table { min-width:720px; }
}
</style>
</head>
<body>
<div class="toolbar">
    @if(!empty($dateAction))
    <form method="GET" action="{{ $dateAction }}" style="display:flex;gap:6px;align-items:center;">
        @foreach(($dateKeep ?? []) as $kk => $vv)<input type="hidden" name="{{ $kk }}" value="{{ $vv }}">@endforeach
        <input type="date" name="date" value="{{ $s['from'] }}" max="{{ now(\App\Services\PartnerStatementService::tz())->toDateString() }}"
               class="btn" style="padding:6px 8px;" onchange="this.form.submit()">
    </form>
    @endif
    <button class="btn btn-primary" onclick="window.print()">🖨 បោះពុម្ព / PDF · Print / Save PDF</button>
    @if(!empty($excelUrl))<a class="btn" href="{{ $excelUrl }}">⬇ Excel</a>@endif
    @foreach(($switchUrls ?? []) as $label => $url)
        <a class="btn" href="{{ $url }}">{{ $label }}</a>
    @endforeach
    <span class="sp"></span>
    @if(!empty($backUrl))<a class="btn" href="{{ $backUrl }}">← ត្រឡប់ · Back</a>@endif
</div>

{{-- ── Successful ─────────────────────────────────────────────────────── --}}
@if($show('successful'))
<div class="page">
    <div class="head">
        <div>
            <div class="brand">ROTEH</div>
            <div class="title">របាយការណ៍កញ្ចប់ដឹកជោគជ័យ</div>
            <div class="en">Successful Deliveries Statement</div>
        </div>
        <div class="meta">
            <b>{{ $s['statement_no'] }}-S</b><br>
            ថ្ងៃបិទបញ្ជី · Closing date: <b>{{ $dateLabel }}</b><br>
            <span class="en">Generated {{ \Carbon\Carbon::parse($s['generated_at'])->format('d/m/Y H:i') }}</span>
        </div>
    </div>
    <div class="info">
        <div><span>ដៃគូ · Partner:</span> <b>{{ $s['partner']['business_name'] }}</b></div>
        <div><span>ទូរស័ព្ទ · Phone:</span> {{ $s['partner']['phone'] }}</div>
        @if($s['partner']['address'])<div style="grid-column:1/-1"><span>អាសយដ្ឋាន · Address:</span> {{ $s['partner']['address'] }}</div>@endif
    </div>
    @php
        $okRows = collect($s['successful']);
        $okPays = $okRows->sum('roteh_pays_partner');
        $okOwes = $okRows->sum('partner_pays_roteh');
        $okBal  = $okPays - $okOwes;
    @endphp
    <div class="cards" style="grid-template-columns:repeat(5,1fr);">
        <div class="card"><div class="v">{{ $sum['successful_count'] }}</div><div class="l">កញ្ចប់ជោគជ័យ · Delivered<br>COD {{ $sum['cod_packages'] }} · គ្មាន COD {{ $sum['non_cod_packages'] }}</div></div>
        <div class="card"><div class="v">{{ $k($sum['total_collected']) }} ៛</div><div class="l">ប្រាក់សរុបបានប្រមូល · Total collected (COD + fee)</div></div>
        <div class="card"><div class="v">{{ $k($sum['service_fees']) }} ៛</div><div class="l">ថ្លៃដឹក ROTEH · ROTEH delivery fees</div></div>
        <div class="card hl"><div class="v">{{ $k($okPays) }} ៛</div><div class="l">ROTEH ត្រូវបង់ដៃគូ · ROTEH pays partner</div></div>
        <div class="card {{ $okOwes ? 'neg' : '' }}"><div class="v">{{ $k($okOwes) }} ៛</div><div class="l">ដៃគូត្រូវបង់ ROTEH · Partner pays ROTEH</div></div>
    </div>
    <div class="tbl"><table>
        <thead><tr>
            <th>#</th>
            <th>លេខកូដ<span class="en">Code / Ref</span></th>
            <th>ពេលដឹកដល់<span class="en">Delivered at</span></th>
            <th>អ្នកទទួល<span class="en">Receiver</span></th>
            <th class="r">COD<span class="en">Item price</span></th>
            <th class="r">ថ្លៃដឹក<span class="en">Delivery fee</span></th>
            <th>អ្នកបង់ថ្លៃដឹក<span class="en">Fee paid by</span></th>
            <th class="r">សរុបបានប្រមូល<span class="en">Total collected</span></th>
            <th class="r">ROTEH បង់ដៃគូ<span class="en">ROTEH pays partner</span></th>
            <th class="r">ដៃគូបង់ ROTEH<span class="en">Partner pays ROTEH</span></th>
        </tr></thead>
        <tbody>
        @forelse($s['successful'] as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><span class="code">{{ $r['code'] }}</span>@if($r['reference'])<div class="small">#{{ $r['reference'] }}</div>@endif
                    <div class="small">{{ $sizes[$r['package_size']] ?? $r['package_size'] }}@if($r['express']) · ⚡@endif</div></td>
                <td>{{ \Illuminate\Support\Str::after($r['completed_at'], ' ') }}<div class="small">{{ $r['driver'] }}</div></td>
                <td>{{ $r['receiver_name'] }}<div class="small">{{ $r['receiver_phone'] }}</div><div class="small">{{ \Illuminate\Support\Str::limit($r['dropoff_address'], 50) }}</div></td>
                <td class="r">{{ $r['cod_collected'] ? $k($r['cod_collected']) : 'គ្មាន COD' }}</td>
                <td class="r">{{ $k($r['fee']) }}</td>
                <td class="small">{{ $paidBy[$r['fee_paid_by']] ?? $r['fee_paid_by'] }}</td>
                <td class="r">{{ $k($r['total_collected']) }}</td>
                <td class="r" style="color:#047857;"><b>{{ $r['roteh_pays_partner'] ? $k($r['roteh_pays_partner']) : '—' }}</b></td>
                <td class="r" style="color:#b91c1c;"><b>{{ $r['partner_pays_roteh'] ? $k($r['partner_pays_roteh']) : '—' }}</b></td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">គ្មានកញ្ចប់ដឹកជោគជ័យនៅថ្ងៃនេះ · No delivered packages</td></tr>
        @endforelse
        </tbody>
        @if(count($s['successful']))
        <tfoot><tr>
            <td colspan="4">សរុប · Total ({{ $sum['successful_count'] }})</td>
            <td class="r">{{ $k($sum['cod_collected']) }}</td>
            <td class="r">{{ $k($sum['service_fees']) }}</td>
            <td></td>
            <td class="r">{{ $k($sum['total_collected']) }}</td>
            <td class="r">{{ $k($okPays) }}</td>
            <td class="r">{{ $k($okOwes) }}</td>
        </tr></tfoot>
        @endif
    </table></div>
    <div class="balance {{ $okBal < 0 ? 'owe' : '' }}">
        <div>{{ $okBal < 0 ? 'ដៃគូត្រូវបង់ ROTEH សរុប · Partner pays ROTEH (balance)' : 'ROTEH ត្រូវបង់ដៃគូសរុប · ROTEH pays partner (balance)' }}</div>
        <div class="amt">{{ $k(abs($okBal)) }} ៛</div>
    </div>
    <p class="note">
        សរុបបានប្រមូល = COD + ថ្លៃដឹក (ពេលអ្នកទទួលបង់ថ្លៃដឹក)។ ROTEH បង់ដៃគូ = COD − ថ្លៃដឹកដែលដៃគូមិនទាន់បង់។
        កញ្ចប់គ្មាន COD ហើយដៃគូជាអ្នកបង់ថ្លៃដឹក → ដៃគូបង់ថ្លៃដឹកឲ្យ ROTEH។
        · Total collected = COD + delivery fee when the receiver pays it. ROTEH pays the partner the COD minus any delivery fee the partner has not paid; for a package without COD the partner pays ROTEH the delivery fee. Amounts in KHR (៛).
    </p>
    <div class="sign"><div>ROTEH (អ្នករៀបចំ · Prepared by)</div><div>ដៃគូ (អ្នកទទួល · Received by)</div></div>
</div>
@endif

{{-- ── Unsuccessful ───────────────────────────────────────────────────── --}}
@if($show('unsuccessful'))
<div class="page">
    <div class="head">
        <div>
            <div class="brand">ROTEH</div>
            <div class="title fail">របាយការណ៍កញ្ចប់ដឹកមិនបានជោគជ័យ</div>
            <div class="en">Unsuccessful Deliveries Statement</div>
        </div>
        <div class="meta">
            <b>{{ $s['statement_no'] }}-U</b><br>
            ថ្ងៃបិទបញ្ជី · Closing date: <b>{{ $dateLabel }}</b><br>
            <span class="en">Generated {{ \Carbon\Carbon::parse($s['generated_at'])->format('d/m/Y H:i') }}</span>
        </div>
    </div>
    <div class="info">
        <div><span>ដៃគូ · Partner:</span> <b>{{ $s['partner']['business_name'] }}</b></div>
        <div><span>ទូរស័ព្ទ · Phone:</span> {{ $s['partner']['phone'] }}</div>
    </div>
    <div class="cards">
        <div class="card neg"><div class="v">{{ $sum['unsuccessful_count'] }}</div><div class="l">មិនជោគជ័យ · Unsuccessful</div></div>
        <div class="card"><div class="v">{{ $sum['returned_count'] }}</div><div class="l">ប្រគល់មកហាងវិញ · Returned</div></div>
        <div class="card"><div class="v">{{ $sum['returning_count'] }}</div><div class="l">កំពុងយកត្រឡប់ · Returning</div></div>
        <div class="card"><div class="v">{{ $k($sum['cod_not_collected']) }} ៛</div><div class="l">COD មិនបានប្រមូល · COD not collected</div></div>
    </div>
    <div class="tbl"><table>
        <thead><tr>
            <th>#</th>
            <th>លេខកូដ<span class="en">Code / Ref</span></th>
            <th>ស្ថានភាព<span class="en">Status</span></th>
            <th>មូលហេតុ<span class="en">Reason</span></th>
            <th>អ្នកទទួល<span class="en">Receiver</span></th>
            <th class="r">COD<span class="en">Not collected</span></th>
            <th class="r">តម្លៃសេវា<span class="en">Service fee</span></th>
            <th class="r">ដៃគូបង់ ROTEH<span class="en">Partner pays ROTEH</span></th>
        </tr></thead>
        <tbody>
        @forelse($s['unsuccessful'] as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><span class="code">{{ $r['code'] }}</span>@if($r['reference'])<div class="small">#{{ $r['reference'] }}</div>@endif</td>
                <td>
                    <span class="tag {{ ['failed' => 't-fail', 'returned' => 't-ret', 'cancelled' => 't-can'][$r['status']] ?? '' }}">{{ PS::UNSUCCESSFUL_STATUS[$r['status']][1] ?? $r['status'] }}</span>
                    <div class="small">{{ \Illuminate\Support\Str::after($r['closed_at'] ?? '', ' ') }} @if($r['driver'])· {{ $r['driver'] }}@endif</div>
                </td>
                <td>@if($r['reason']){{ PS::REASONS[$r['reason']][1] ?? $r['reason'] }}<div class="small">{{ PS::REASONS[$r['reason']][0] ?? '' }}</div>@endif
                    @if($r['note'])<div class="small">“{{ $r['note'] }}”</div>@endif</td>
                <td>{{ $r['receiver_name'] }}<div class="small">{{ $r['receiver_phone'] }}</div></td>
                <td class="r">{{ $r['cod_amount'] ? $k($r['cod_amount']) : '—' }}</td>
                <td class="r">{{ $k($r['fee']) }}</td>
                <td class="r" style="color:#b91c1c;"><b>{{ $r['fee_charged'] ? $k($r['fee_charged']) : '—' }}</b></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">គ្មានកញ្ចប់មិនជោគជ័យនៅថ្ងៃនេះ · No unsuccessful packages</td></tr>
        @endforelse
        </tbody>
        @if(count($s['unsuccessful']))
        <tfoot><tr>
            <td colspan="5">សរុប · Total ({{ $sum['unsuccessful_count'] }})</td>
            <td class="r">{{ $k($sum['cod_not_collected']) }}</td>
            <td class="r">{{ $k(collect($s['unsuccessful'])->sum('fee')) }}</td>
            <td class="r">{{ $k($sum['failed_fees']) }}</td>
        </tr></tfoot>
        @endif
    </table></div>
    <p class="note">
        កញ្ចប់ដែលមិនបានដឹកដល់ មិនបានប្រមូល COD ទេ។
        @if($sum['failed_fee_percent'] > 0) គិតថ្លៃ {{ rtrim(rtrim(number_format($sum['failed_fee_percent'], 2), '0'), '.') }}% នៃតម្លៃសេវា សម្រាប់កញ្ចប់ដែលបានព្យាយាមដឹក។
        @else មិនគិតថ្លៃសេវាសម្រាប់កញ្ចប់មិនជោគជ័យ។ @endif
        · No COD is collected for unsuccessful packages.
        @if($sum['failed_fee_percent'] > 0) {{ rtrim(rtrim(number_format($sum['failed_fee_percent'], 2), '0'), '.') }}% of the service fee is charged for attempted deliveries. @else No service fee is charged. @endif
    </p>
    @php $day = $sum['net_to_partner']; @endphp
    <div class="balance {{ $day < 0 ? 'owe' : '' }}">
        <div>
            សមតុល្យប្រចាំថ្ងៃ (ជោគជ័យ + មិនជោគជ័យ) · Day balance (successful + unsuccessful)<br>
            <span class="en">ROTEH pays partner {{ $k($sum['roteh_pays_partner']) }} ៛ − Partner pays ROTEH {{ $k($sum['partner_pays_roteh']) }} ៛</span>
        </div>
        <div style="text-align:right;">
            <div class="en">{{ $day < 0 ? 'ដៃគូត្រូវបង់ ROTEH · Partner pays ROTEH' : 'ROTEH ត្រូវបង់ដៃគូ · ROTEH pays partner' }}</div>
            <div class="amt">{{ $k(abs($day)) }} ៛</div>
        </div>
    </div>
    <div class="sign"><div>ROTEH (អ្នករៀបចំ · Prepared by)</div><div>ដៃគូ (អ្នកទទួល · Received by)</div></div>
</div>
@endif

{{-- ── All packages (every status) ────────────────────────────────────── --}}
@if($show('all'))
@php($sc = $sum['status_counts'])
<div class="page">
    <div class="head">
        <div>
            <div class="brand">ROTEH</div>
            <div class="title">របាយការណ៍កញ្ចប់ទាំងអស់ (គ្រប់ស្ថានភាព)</div>
            <div class="en">All Packages Report — every status</div>
        </div>
        <div class="meta">
            <b>{{ $s['statement_no'] }}-A</b><br>
            ថ្ងៃ · Date: <b>{{ $dateLabel }}</b><br>
            <span class="en">Generated {{ \Carbon\Carbon::parse($s['generated_at'])->format('d/m/Y H:i') }}</span>
        </div>
    </div>
    <div class="info">
        <div><span>ដៃគូ · Partner:</span> <b>{{ $s['partner']['business_name'] }}</b></div>
        <div><span>ទូរស័ព្ទ · Phone:</span> {{ $s['partner']['phone'] }}</div>
    </div>
    <div class="cards" style="grid-template-columns:repeat(4,1fr);">
        <div class="card hl"><div class="v">{{ $sum['total_count'] }}</div><div class="l">សរុប · Total</div></div>
        @foreach(\App\Services\PartnerStatementService::STATUS_LABELS as $g => $lbl)
            <div class="card {{ in_array($g, ['failed','returned']) && $sc[$g] ? 'neg' : '' }}"><div class="v">{{ $sc[$g] }}</div><div class="l">{{ $lbl[1] }} · {{ $lbl[0] }}</div></div>
        @endforeach
    </div>
    <div class="tbl"><table>
        <thead><tr>
            <th>#</th>
            <th>លេខកូដ<span class="en">Code / Ref</span></th>
            <th>បង្កើត<span class="en">Created</span></th>
            <th>ស្ថានភាព<span class="en">Status</span></th>
            <th>អ្នកទទួល<span class="en">Receiver</span></th>
            <th>អ្នកបើកបរ<span class="en">Driver</span></th>
            <th class="r">COD</th>
            <th class="r">តម្លៃសេវា<span class="en">Service fee</span></th>
        </tr></thead>
        <tbody>
        @forelse($s['all'] as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><span class="code">{{ $r['code'] }}</span>@if($r['reference'])<div class="small">#{{ $r['reference'] }}</div>@endif</td>
                <td>{{ \Illuminate\Support\Str::after($r['created_at'], ' ') }}<div class="small">{{ \Illuminate\Support\Str::before($r['created_at'], ' ') }}</div></td>
                <td><span class="tag {{ $groupTag[$r['group']] ?? '' }}">{{ PS::STATUS_LABELS[$r['group']][1] ?? $r['group'] }}</span>
                    @if($r['reason'])<div class="small">{{ PS::REASONS[$r['reason']][1] ?? '' }}</div>@endif
                    @if($r['closed_at'])<div class="small">{{ \Illuminate\Support\Str::after($r['closed_at'], ' ') }}</div>@endif</td>
                <td>{{ $r['receiver_name'] }}<div class="small">{{ $r['receiver_phone'] }}</div></td>
                <td class="small">{{ $r['driver'] ?? '—' }}</td>
                <td class="r">{{ $r['cod_amount'] ? $k($r['cod_amount']) : '—' }}</td>
                <td class="r">{{ $k($r['fee']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">គ្មានកញ្ចប់នៅថ្ងៃនេះ · No packages on this day</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <p class="note">កញ្ចប់ដែលបង្កើតនៅថ្ងៃនេះ ហើយនៅមិនទាន់បញ្ចប់ និងកញ្ចប់ដែលបានបិទនៅថ្ងៃនេះ · Packages created this day that are still open, plus packages closed this day.</p>
</div>
@endif

@if(!empty($autoPrint))
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
@endif
</body>
</html>
