@extends('admin.layout')
@section('title', 'Partner Statements')
@section('page-title', 'Partner Statements')

@php
    use App\Services\PartnerStatementService as PS;
    $L = PS::STATUS_LABELS;
    $tone = ['pending' => 'warning', 'confirmed' => 'info', 'on_the_way' => 'primary', 'completed' => 'success',
             'failed' => 'danger', 'returned' => 'danger', 'cancelled' => 'muted'];
    $icon = ['pending' => 'fa-hourglass-half', 'confirmed' => 'fa-magnifying-glass-location', 'on_the_way' => 'fa-truck-fast',
             'completed' => 'fa-circle-check', 'failed' => 'fa-phone-slash', 'returned' => 'fa-rotate-left', 'cancelled' => 'fa-ban'];
    $q = fn (array $extra = []) => array_merge(request()->except('page'), $extra);
@endphp

@section('content')

<x-admin.page-toolbar subtitle="Daily statements after closing the books. Every status per partner; print Successful / Unsuccessful / All statuses for each partner, or the whole list.">
    <form method="GET" action="{{ route('admin.partner-statements') }}" class="d-flex align-items-center" style="gap:6px;">
        @foreach(request()->except(['date', 'page']) as $k => $v)
            @if(filled($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <label class="mb-0 text-muted">Closing date</label>
        <input type="date" name="date" value="{{ $date }}" max="{{ now(PS::tz())->toDateString() }}"
               class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
    </form>
    <a class="btn btn-outline-primary" target="_blank" href="{{ route('admin.partner-statements.overview', $q(['date' => $date, 'print' => 1])) }}">
        <i class="fas fa-print mr-1"></i> Print list</a>
    <a class="btn btn-outline-success" href="{{ route('admin.partner-statements.overview.excel', $q(['date' => $date])) }}">
        <i class="fas fa-file-excel mr-1"></i> Excel</a>
    <a class="btn btn-outline-dark" target="_blank" href="{{ route('admin.partner-statements.drivers', ['date' => $date]) }}"
       title="Cash each ROTEH staff driver hands over (COD + receiver-paid fees)">
        <i class="fas fa-motorcycle mr-1"></i> Driver cash</a>
</x-admin.page-toolbar>

{{-- Status cards — click to filter --}}
<div class="row mb-2">
    <div class="col-6 col-md-3 col-xl mb-2">
        <x-admin.stat-card label="All packages" :value="number_format($totals['total'])" icon="fa-boxes-stacked" tone="primary"
                           :sub="number_format($totals['partners']) . ' partners'"
                           :href="route('admin.partner-statements', $q(['status' => null]))" :active="!request('status')" />
    </div>
    @foreach([['pending', 'pending'], ['on_the_way', 'open'], ['completed', 'successful'], ['returned', 'returned'], ['cancelled', 'unsuccessful']] as [$g, $filter])
        <div class="col-6 col-md-3 col-xl mb-2">
            <x-admin.stat-card :label="$g === 'returned' ? 'Not delivered / Returned' : ($g === 'cancelled' ? 'Unsuccessful (all)' : ($g === 'on_the_way' ? 'Open (pending → on the way)' : $L[$g][0]))"
                               :value="number_format(match ($g) {
                                   'returned' => $totals['counts']['failed'] + $totals['counts']['returned'],
                                   'cancelled' => $totals['unsuccessful'],
                                   'on_the_way' => $totals['open'],
                                   default => $totals['counts'][$g] })"
                               :icon="$icon[$g]" :tone="$tone[$g] === 'muted' ? 'danger' : $tone[$g]"
                               :href="route('admin.partner-statements', $q(['status' => $filter]))" :active="request('status') === $filter" />
        </div>
    @endforeach
</div>

<div class="row mb-3">
    @foreach([
        ['Total collected (COD + fee)', $totals['collected'], 'var(--ink-900)'],
        ['ROTEH delivery fees', $totals['fees'], 'var(--ink-900)'],
        ['ROTEH pays partners', $totals['roteh_pays'], 'var(--success)'],
        ['Partners pay ROTEH', $totals['partner_pays'], 'var(--danger)'],
        ['Balance (ROTEH → partners)', $totals['net'], $totals['net'] < 0 ? 'var(--danger)' : 'var(--success)'],
    ] as [$lbl, $val, $col])
        <div class="col-6 col-md mb-2"><div class="card mb-0"><div class="card-body py-2">
            <div class="text-muted tw:text-xs">{{ $lbl }}</div><div style="font-size:1.15rem;font-weight:700;color:{{ $col }};">{{ number_format($val) }} ៛</div></div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.partner-statements')" placeholder="Search partner, business, phone or email"
                            :count="$rows->total()" noun="partner" :keep="['date']">
            <x-admin.filter-select name="status" all="All statuses" :options="[
                'pending' => 'Has pending (waiting confirm)',
                'open' => 'Has open (pending → on the way)',
                'successful' => 'Has successful',
                'unsuccessful' => 'Has unsuccessful',
                'returned' => 'Has not delivered / returned',
                'owes' => 'Partner owes ROTEH',
            ]" />
            <x-admin.filter-select name="sort" all="Sort: most packages" :options="[
                'name' => 'Sort: name A–Z',
                'unsuccessful' => 'Sort: most unsuccessful',
                'open' => 'Sort: most open',
                'cod' => 'Sort: highest COD',
                'net' => 'Sort: highest net',
            ]" />
            <x-admin.filter-select name="per_page" all="25 / page" :options="['50' => '50 / page', '100' => '100 / page']" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Partner</th>
                    <th class="text-right">Total</th>
                    <th>Status breakdown</th>
                    <th class="text-right">Total collected</th>
                    <th class="text-right">ROTEH fees</th>
                    <th class="text-right">ROTEH pays</th>
                    <th class="text-right">Partner pays</th>
                    <th class="text-right">Balance</th>
                    <th class="text-center">Statements</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                @php $p = $r['partner']; @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $r['name'] }}</div>
                        <div class="text-muted tw:text-xs">{{ $r['phone'] }}</div>
                    </td>
                    <td class="text-right" style="font-weight:700;">{{ $r['total'] }}</td>
                    <td style="white-space:normal;min-width:260px;">
                        @foreach($L as $g => $lbl)
                            @if($r['counts'][$g] > 0)
                                <span class="status-pill tone-{{ $tone[$g] }} mb-1" title="{{ $lbl[0] }} · {{ $lbl[1] }}">
                                    <i class="fas {{ $icon[$g] }} mr-1"></i>{{ $lbl[0] === 'Confirmed — finding driver' ? 'Confirmed' : ($lbl[0] === 'Not delivered — returning' ? 'Returning' : $lbl[0]) }} {{ $r['counts'][$g] }}
                                </span>
                            @endif
                        @endforeach
                    </td>
                    <td class="text-right">{{ number_format($r['total_collected']) }} ៛<div class="text-muted tw:text-xs">COD {{ number_format($r['cod_collected']) }}</div></td>
                    <td class="text-right">{{ number_format($r['service_fees']) }} ៛</td>
                    <td class="text-right text-success">{{ number_format($r['roteh_pays_partner']) }} ៛</td>
                    <td class="text-right text-danger">{{ number_format($r['partner_pays_roteh']) }} ៛</td>
                    <td class="text-right" style="font-weight:700;color:{{ $r['net_to_partner'] < 0 ? 'var(--danger)' : 'var(--success)' }};">
                        {{ number_format(abs($r['net_to_partner'])) }} ៛
                        <div class="tw:text-xs" style="font-weight:400;">{{ $r['net_to_partner'] < 0 ? 'Partner pays ROTEH' : 'ROTEH pays partner' }}</div></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-success" target="_blank" title="Print successful" href="{{ route('admin.partner-statements.show', [$p, 'date' => $date, 'type' => 'successful', 'print' => 1]) }}">
                                <i class="fas fa-print mr-1"></i>Successful</a>
                            <a class="btn btn-outline-danger" target="_blank" title="Print unsuccessful" href="{{ route('admin.partner-statements.show', [$p, 'date' => $date, 'type' => 'unsuccessful', 'print' => 1]) }}">
                                <i class="fas fa-print mr-1"></i>Unsuccessful</a>
                            <a class="btn btn-outline-primary" target="_blank" title="All statuses" href="{{ route('admin.partner-statements.show', [$p, 'date' => $date, 'type' => 'all']) }}">
                                <i class="fas fa-list mr-1"></i>All</a>
                            <a class="btn btn-outline-secondary" title="Excel" href="{{ route('admin.partner-statements.excel', [$p, 'date' => $date]) }}"><i class="fas fa-file-excel"></i></a>
                        </div>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="9" icon="fa-file-invoice" title="No partners match"
                                   hint="Try another closing date, search or status." :clear="route('admin.partner-statements', ['date' => $date])" />
            @endforelse
            </tbody>
            @if($rows->total() > 0)
            <tfoot>
                <tr style="font-weight:700;background:var(--surface-2, #f8fafc);">
                    <td>Total ({{ number_format($totals['partners']) }} partners{{ $rows->hasPages() ? ', all pages' : '' }})</td>
                    <td class="text-right">{{ number_format($totals['total']) }}</td>
                    <td class="tw:text-xs text-muted" style="white-space:normal;">
                        @foreach($L as $g => $lbl) @if($totals['counts'][$g]) {{ $lbl[0] }} {{ $totals['counts'][$g] }} · @endif @endforeach
                    </td>
                    <td class="text-right">{{ number_format($totals['collected']) }} ៛</td>
                    <td class="text-right">{{ number_format($totals['fees']) }} ៛</td>
                    <td class="text-right text-success">{{ number_format($totals['roteh_pays']) }} ៛</td>
                    <td class="text-right text-danger">{{ number_format($totals['partner_pays']) }} ៛</td>
                    <td class="text-right">{{ number_format($totals['net']) }} ៛</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    <x-admin.pagination :paginator="$rows" />
</div>
@endsection
