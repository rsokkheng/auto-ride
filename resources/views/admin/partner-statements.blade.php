@extends('admin.layout')
@section('title', 'Partner Statements')
@section('page-title', 'Partner Statements')

@section('content')

<x-admin.page-toolbar subtitle="Daily statements after closing the books — print the Successful and Unsuccessful reports and hand them to each Partner. Partners see the same statements in the app and on the Partner website.">
    <form method="GET" action="{{ route('admin.partner-statements') }}" class="d-flex align-items-center" style="gap:6px;">
        <label class="mb-0 text-muted">Closing date</label>
        <input type="date" name="date" value="{{ $date }}" max="{{ now(\App\Services\PartnerStatementService::tz())->toDateString() }}"
               class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
    </form>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Partners with activity" :value="number_format($rows->count())" icon="fa-handshake" tone="primary" />
    <x-admin.stat-card label="Successful" :value="number_format($totals['successful'])" icon="fa-circle-check" tone="success"
                       :sub="number_format($totals['unsuccessful']) . ' unsuccessful'" />
    <x-admin.stat-card label="COD collected" :value="number_format($totals['cod']) . ' ៛'" icon="fa-money-bill-wave" tone="info" />
    <x-admin.stat-card label="Payable to partners" :value="number_format($totals['net']) . ' ៛'" icon="fa-hand-holding-dollar" tone="warning"
                       :sub="'Service fees ' . number_format($totals['fees']) . ' ៛'" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Partner</th>
                    <th class="text-right">Successful</th>
                    <th class="text-right">Unsuccessful</th>
                    <th class="text-right">COD collected</th>
                    <th class="text-right">Service fees</th>
                    <th class="text-right">Net to partner</th>
                    <th class="text-center">Statements</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                @php
                    $p = $r['partner'];
                    $s = $r['summary'];
                @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $p->partnerProfile?->business_name ?: ($p->company_name ?: $p->name) }}</div>
                        <div class="text-muted tw:text-xs">{{ $p->partnerProfile?->phone ?: $p->phone }}</div>
                    </td>
                    <td class="text-right"><span class="text-success" style="font-weight:600;">{{ $s['successful_count'] }}</span></td>
                    <td class="text-right">
                        <span class="{{ $s['unsuccessful_count'] ? 'text-danger' : 'text-muted' }}" style="font-weight:600;">{{ $s['unsuccessful_count'] }}</span>
                        @if($s['unsuccessful_count'])<div class="text-muted tw:text-xs">{{ $s['returned_count'] }} returned · {{ $s['returning_count'] }} returning · {{ $s['cancelled_count'] }} cancelled</div>@endif
                    </td>
                    <td class="text-right">{{ number_format($s['cod_collected']) }} ៛</td>
                    <td class="text-right">{{ number_format($s['service_fees']) }} ៛</td>
                    <td class="text-right" style="font-weight:700;color:{{ $s['net_to_partner'] < 0 ? 'var(--danger)' : 'var(--ink-900)' }};">{{ number_format($s['net_to_partner']) }} ៛</td>
                    <td class="text-center">
                        <a class="btn btn-sm btn-success" target="_blank" href="{{ route('admin.partner-statements.show', [$p, 'date' => $date, 'type' => 'successful', 'print' => 1]) }}" title="Print successful">
                            <i class="fas fa-print mr-1"></i>Successful</a>
                        <a class="btn btn-sm btn-outline-danger" target="_blank" href="{{ route('admin.partner-statements.show', [$p, 'date' => $date, 'type' => 'unsuccessful', 'print' => 1]) }}" title="Print unsuccessful">
                            <i class="fas fa-print mr-1"></i>Unsuccessful</a>
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('admin.partner-statements.show', [$p, 'date' => $date]) }}" title="View both"><i class="fas fa-eye"></i></a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.partner-statements.excel', [$p, 'date' => $date]) }}" title="Excel"><i class="fas fa-file-excel"></i></a>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="7" icon="fa-file-invoice" title="No partner packages closed on this date"
                                   hint="Pick another closing date above." :clear="route('admin.partner-statements')" />
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
