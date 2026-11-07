@extends('admin.layout')
@section('title', 'Daily Closing')
@section('page-title', 'Daily Closing · បិទបញ្ជីប្រចាំថ្ងៃ')

@php
    $k = fn ($n) => number_format((int) $n);
    $usd = fn ($n) => '$' . number_format((float) $n, 2);
    $driversOpen  = $drivers->whereNull('closing')->count();
    $partnersOpen = $partners->whereNull('closing')->count();
    $locked = (bool) $day;
@endphp

@section('content')

<x-admin.page-toolbar subtitle="Partner packages — 1) close with each staff driver (cash ៛ / $ and bank), 2) close with each partner (statement settled), 3) finish the day. Rate: 1 USD = {{ number_format($rate) }} ៛.">
    <form method="GET" action="{{ route('admin.daily-closing') }}" class="d-flex align-items-center" style="gap:6px;">
        <label class="mb-0 text-muted">Business date</label>
        <input type="date" name="date" value="{{ $date }}" max="{{ now(\App\Services\PartnerStatementService::tz())->toDateString() }}"
               class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
    </form>
    <a class="btn btn-outline-dark" target="_blank" href="{{ route('admin.partner-statements.drivers', ['date' => $date]) }}"><i class="fas fa-motorcycle mr-1"></i>Driver cash sheet</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.partner-statements', ['date' => $date]) }}"><i class="fas fa-file-invoice mr-1"></i>Partner statements</a>
</x-admin.page-toolbar>

@if($errors->any())
    <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

@if($locked)
    <div class="alert alert-success d-flex align-items-center">
        <i class="fas fa-lock mr-2"></i>
        <div><b>Day finished</b> by {{ $day->closer?->name }} at {{ $day->closed_at?->timezone(\App\Services\PartnerStatementService::tz())->format('d/m/Y H:i') }}.
            {{ $day->drivers_closed }} drivers · {{ $day->partners_closed }} partners closed.
            @if($day->note)<span class="text-muted">“{{ $day->note }}”</span>@endif</div>
    </div>
@endif

{{-- Steps --}}
<div class="row mb-3">
    @foreach([
        ['1', 'Close drivers', 'បិទបញ្ជីជាមួយអ្នកដឹក', $drivers->count() - $driversOpen, $drivers->count()],
        ['2', 'Close partners', 'បិទបញ្ជីជាមួយដៃគូ', $partners->count() - $partnersOpen, $partners->count()],
        ['3', 'Finish day', 'បញ្ចប់ប្រចាំថ្ងៃ', $locked ? 1 : 0, 1],
    ] as [$n, $en, $km, $done, $all])
        <div class="col-md-4 mb-2">
            <div class="card mb-0" style="border-left:4px solid {{ $done >= $all && $all > 0 ? 'var(--success)' : 'var(--warning)' }};">
                <div class="card-body py-2">
                    <div class="text-muted tw:text-xs">Step {{ $n }} · {{ $km }}</div>
                    <div style="font-weight:700;">{{ $en }} — {{ $done }}/{{ $all }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- 1. Drivers --}}
<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-motorcycle mr-2"></i>1 · Staff drivers — cash hand-over</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead><tr>
                <th>Driver</th><th class="text-right">Packages</th>
                <th class="text-right">Cash ៛ expected</th><th class="text-right">Cash $ expected</th>
                <th class="text-right">Bank</th><th class="text-right">Returns</th>
                <th>Status</th><th class="text-center">Action</th>
            </tr></thead>
            <tbody>
            @forelse($drivers as $r)
                @php $d = $r['driver']; $e = $r['expected']; $c = $r['closing']; @endphp
                <tr>
                    <td><b>{{ $d->name }}</b><div class="text-muted tw:text-xs">{{ $d->phone }}@if($d->driver_type !== \App\Models\User::STAFF_TYPE) · ⚠ not staff @endif</div></td>
                    <td class="text-right">{{ $e['packages'] }}
                        @if($e['difference_khr'])<div class="tw:text-xs text-danger">diff {{ $k($e['difference_khr']) }} ៛</div>@endif</td>
                    <td class="text-right">{{ $k($e['cash_khr']) }} ៛</td>
                    <td class="text-right">{{ $usd($e['cash_usd']) }}</td>
                    <td class="text-right">{{ $k($e['bank_khr']) }} ៛@if($e['bank_usd'] > 0)<div class="tw:text-xs">{{ $usd($e['bank_usd']) }}</div>@endif</td>
                    <td class="text-right">{{ $e['returned'] + $e['returning'] }}@if($e['returning'])<div class="tw:text-xs text-danger">{{ $e['returning'] }} still out</div>@endif</td>
                    <td>
                        @if($c)
                            <span class="status-pill tone-success"><i class="fas fa-check mr-1"></i>Closed</span>
                            <div class="tw:text-xs text-muted">{{ $k($c->received_cash_khr) }} ៛ + {{ $usd($c->received_cash_usd) }}
                                @if($c->difference_khr) · <span class="text-danger">diff {{ $k($c->difference_khr) }} ៛</span>@endif</div>
                            <div class="tw:text-xs text-muted">{{ $c->closer?->name }} {{ $c->closed_at?->timezone(\App\Services\PartnerStatementService::tz())->format('H:i') }}</div>
                        @else
                            <span class="status-pill tone-warning">Open</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if(!$locked)
                            @if($c)
                                <form method="POST" action="{{ route('admin.daily-closing.driver.reopen', $d) }}" class="d-inline">@csrf
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="return confirm('Reopen {{ addslashes($d->name) }}?')">Reopen</button></form>
                            @else
                                <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#driverModal"
                                        data-action="{{ route('admin.daily-closing.driver.close', $d) }}" data-name="{{ $d->name }}"
                                        data-khr="{{ $e['cash_khr'] }}" data-usd="{{ $e['cash_usd'] }}"
                                        data-bank="{{ $e['bank_khr'] > 0 || $e['bank_usd'] > 0 ? 1 : 0 }}"
                                        data-banktext="{{ $k($e['bank_khr']) }} ៛{{ $e['bank_usd'] > 0 ? ' + ' . $usd($e['bank_usd']) : '' }}">
                                    <i class="fas fa-check mr-1"></i>Close</button>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No staff driver activity on this day.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- 2. Partners --}}
<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-handshake mr-2"></i>2 · Partners — settle the day's statement</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead><tr>
                <th>Partner</th><th class="text-right">Successful</th><th class="text-right">Unsuccessful</th>
                <th class="text-right">COD</th><th class="text-right">Fees</th><th class="text-right">Balance</th>
                <th>Status</th><th class="text-center">Action</th>
            </tr></thead>
            <tbody>
            @forelse($partners as $r)
                @php $p = $r['partner']; $s = $r['summary']; $c = $r['closing']; $net = $c?->net_to_partner ?? ($s['net_to_partner'] ?? 0); @endphp
                <tr>
                    <td><b>{{ $r['name'] }}</b>
                        <div class="tw:text-xs"><a target="_blank" href="{{ route('admin.partner-statements.show', [$p, 'date' => $date]) }}">Statement</a></div></td>
                    <td class="text-right">{{ $c?->successful_count ?? ($s['successful'] ?? 0) }}</td>
                    <td class="text-right">{{ $c?->unsuccessful_count ?? ($s['unsuccessful'] ?? 0) }}</td>
                    <td class="text-right">{{ $k($c?->cod_collected ?? ($s['cod_collected'] ?? 0)) }} ៛</td>
                    <td class="text-right">{{ $k($c?->delivery_fees ?? ($s['service_fees'] ?? 0)) }} ៛</td>
                    <td class="text-right" style="font-weight:700;color:{{ $net < 0 ? 'var(--danger)' : 'var(--success)' }};">
                        {{ $k(abs($net)) }} ៛<div class="tw:text-xs" style="font-weight:400;">{{ $net < 0 ? 'Partner pays ROTEH' : 'ROTEH pays partner' }}</div></td>
                    <td>
                        @if($c)
                            <span class="status-pill tone-success"><i class="fas fa-check mr-1"></i>Closed</span>
                            <div class="tw:text-xs text-muted">{{ ucfirst(str_replace('_', ' ', $c->settle_method)) }}@if($c->settle_ref) · {{ $c->settle_ref }}@endif</div>
                        @else
                            <span class="status-pill tone-warning">Open</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if(!$locked)
                            @if($c)
                                <form method="POST" action="{{ route('admin.daily-closing.partner.reopen', $p) }}" class="d-inline">@csrf
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    <button class="btn btn-sm btn-outline-secondary">Reopen</button></form>
                            @else
                                <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#partnerModal"
                                        data-action="{{ route('admin.daily-closing.partner.close', $p) }}" data-name="{{ $r['name'] }}"
                                        data-net="{{ $k(abs($net)) }} ៛ — {{ $net < 0 ? 'Partner pays ROTEH' : 'ROTEH pays partner' }}">
                                    <i class="fas fa-check mr-1"></i>Close</button>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No partner activity on this day.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- 3. Finish --}}
@if(!$locked)
<div class="card">
    <div class="card-body d-flex flex-wrap align-items-center" style="gap:10px;">
        <div class="mr-auto">
            <b>3 · Finish the day</b>
            <div class="text-muted tw:text-xs">
                @if($driversOpen || $partnersOpen)
                    Still open: {{ $driversOpen }} driver(s), {{ $partnersOpen }} partner(s).
                @else
                    Everything is closed — finishing locks this day.
                @endif
            </div>
        </div>
        <form method="POST" action="{{ route('admin.daily-closing.finish') }}" class="d-flex" style="gap:6px;">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="text" name="note" class="form-control form-control-sm" placeholder="Note (optional)" style="width:220px;">
            <button class="btn btn-primary btn-sm" @disabled($driversOpen || $partnersOpen)
                    onclick="return confirm('Finish {{ $date }}? The day will be locked.')"><i class="fas fa-lock mr-1"></i>Finish day</button>
        </form>
    </div>
</div>
@endif

{{-- Driver close modal --}}
<div class="modal fade" id="driverModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content" id="driverForm">@csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="modal-header"><h5 class="modal-title">Close driver — <span class="js-name"></span></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body">
        <p class="text-muted mb-2">Expected cash: <b class="js-exp"></b></p>
        <div class="form-row">
            <div class="form-group col-6"><label>Cash ៛ received</label><input type="number" name="received_cash_khr" min="0" step="100" class="form-control js-khr" required></div>
            <div class="form-group col-6"><label>Cash $ received</label><input type="number" name="received_cash_usd" min="0" step="0.01" class="form-control js-usd"></div>
        </div>
        <div class="form-check mb-2 js-bankrow">
            <input class="form-check-input" type="checkbox" name="bank_verified" value="1" id="bankVerified">
            <label class="form-check-label" for="bankVerified">Bank transfers checked on the bank statement (<span class="js-bank"></span>)</label>
        </div>
        <div class="form-group mb-0"><label>Note <small class="text-muted">— required if the cash differs</small></label>
            <input type="text" name="note" maxlength="500" class="form-control"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-success">Close driver</button></div>
</form></div></div>

{{-- Partner close modal --}}
<div class="modal fade" id="partnerModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content" id="partnerForm">@csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="modal-header"><h5 class="modal-title">Close partner — <span class="js-name"></span></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body">
        <p class="mb-2">Balance: <b class="js-net"></b></p>
        <div class="form-group"><label>Settled by</label>
            <select name="settle_method" class="form-control" required>
                <option value="cash">Cash</option>
                <option value="bank">Bank transfer</option>
                <option value="wallet">ROTEH Pay wallet</option>
                <option value="carry_over">Carry over to next statement</option>
            </select></div>
        <div class="form-group"><label>Reference <small class="text-muted">(bank ref / receipt no.)</small></label><input type="text" name="settle_ref" maxlength="100" class="form-control"></div>
        <div class="form-group mb-0"><label>Note</label><input type="text" name="note" maxlength="500" class="form-control"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-success">Close partner</button></div>
</form></div></div>
@endsection

@push('scripts')
<script>
$('#driverModal').on('show.bs.modal', function (e) {
    const b = $(e.relatedTarget), m = $(this);
    $('#driverForm').attr('action', b.data('action'));
    m.find('.js-name').text(b.data('name'));
    m.find('.js-exp').text(Number(b.data('khr')).toLocaleString() + ' ៛ + $' + Number(b.data('usd')).toFixed(2));
    m.find('.js-khr').val(b.data('khr')); m.find('.js-usd').val(b.data('usd'));
    m.find('.js-bank').text(b.data('banktext'));
    m.find('.js-bankrow').toggle(b.data('bank') == 1);
});
$('#partnerModal').on('show.bs.modal', function (e) {
    const b = $(e.relatedTarget);
    $('#partnerForm').attr('action', b.data('action'));
    $(this).find('.js-name').text(b.data('name'));
    $(this).find('.js-net').text(b.data('net'));
});
</script>
@endpush
