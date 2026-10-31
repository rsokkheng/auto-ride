@extends('admin.layout')
@section('title', 'Payment Settlements')
@section('page-title', 'Payment Settlements')
@section('content')

<x-admin.page-toolbar subtitle="Periodic payouts to drivers and partners: earnings minus commission, adjusted for cash they collected.">
    <a href="{{ route('admin.settlements.export', request()->query()) }}" class="btn btn-secondary"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
    <a href="{{ route('admin.settlements.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Generate Settlement</a>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="6">
    <x-admin.stat-card label="All" :value="number_format($summary->total ?? 0)" icon="fa-layer-group" tone="muted"
                       :href="route('admin.settlements.index')" :active="!request('status')" />
    <x-admin.stat-card label="Pending" :value="number_format($summary->pending_count ?? 0)" icon="fa-clock" tone="warning"
                       :href="route('admin.settlements.index', ['status' => 'pending'])" :active="request('status') === 'pending'" />
    <x-admin.stat-card label="Approved" :value="number_format($summary->approved_count ?? 0)" icon="fa-thumbs-up" tone="info"
                       :href="route('admin.settlements.index', ['status' => 'approved'])" :active="request('status') === 'approved'" />
    <x-admin.stat-card label="Processing" :value="number_format($summary->processing_count ?? 0)" icon="fa-spinner" tone="primary"
                       :href="route('admin.settlements.index', ['status' => 'processing'])" :active="request('status') === 'processing'" />
    <x-admin.stat-card label="Total Paid" :value="number_format($summary->total_paid ?? 0) . ' ៛'" icon="fa-check-double" tone="success"
                       :href="route('admin.settlements.index', ['status' => 'paid'])" :active="request('status') === 'paid'" />
    <x-admin.stat-card label="Still to Pay" :value="number_format($summary->total_pending_amount ?? 0) . ' ៛'" icon="fa-hourglass-half" tone="danger" />
</x-admin.stat-grid>

{{-- Bulk actions --}}
<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.settlements.index')" placeholder="Driver or partner name" :count="$settlements->total()" noun="settlement">
            <x-admin.filter-select name="status" :options="collect(['draft','pending','approved','processing','paid','failed','cancelled'])->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all()" all="All statuses" />
            <x-admin.filter-select name="type" :options="['driver' => 'Drivers', 'partner' => 'Partners']" all="Drivers & partners" />
            <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" aria-label="From date" style="min-width:0;width:145px;">
            <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" aria-label="To date" style="min-width:0;width:145px;">
        </x-admin.filter-bar>
    </div>
    <form id="bulkForm" method="POST" class="m-0">
    @csrf
    <div class="card-body py-2 d-flex align-items-center" style="gap:.5rem;background:var(--ink-50);border-bottom:1px solid var(--ink-200);">
        <span class="text-muted tw:text-xs">With selected:</span>
        <button type="button" class="btn btn-sm btn-outline-warning" onclick="submitBulk('{{ route('admin.settlements.bulk-approve') }}')"><i class="fas fa-thumbs-up mr-1"></i>Approve</button>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="submitBulk('{{ route('admin.settlements.bulk-process') }}')"><i class="fas fa-play mr-1"></i>Process</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="checkAll" aria-label="Select all"></th>
                    <th>#</th>
                    <th>Type</th>
                    <th>Payee</th>
                    <th>Period</th>
                    <th class="text-right">Gross</th>
                    <th class="text-right">Commission</th>
                    <th class="text-right">COD</th>
                    <th class="text-right">Net Payout</th>
                    <th class="text-center">Status</th>
                    <th>Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($settlements as $s)
                @php
                    $gross = $s->settlement_type === 'driver' ? $s->gross_earnings : $s->delivery_fees;
                    $cod   = $s->settlement_type === 'driver' ? $s->cod_collected : $s->cod_handled;
                @endphp
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $s->id }}"></td>
                    <td><a href="{{ route('admin.settlements.show', $s->id) }}" class="font-weight-bold">#{{ $s->id }}</a></td>
                    <td>
                        @if($s->settlement_type === 'driver')
                        <span class="badge badge-info"><i class="fas fa-id-badge mr-1"></i>Driver</span>
                        @else
                        <span class="badge badge-warning"><i class="fas fa-handshake mr-1"></i>Partner</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $s->entity_name }}</div>
                        <div class="text-muted tw:text-xs">{{ $s->entity_phone }}</div>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($s->period_start)->format('d M') }} – {{ \Carbon\Carbon::parse($s->period_end)->format('d M Y') }}</td>
                    <td class="text-right">{{ number_format($gross) }}</td>
                    <td class="text-right text-danger">
                        @if($s->settlement_type === 'driver')
                        {{ number_format($s->commission_total) }}
                        @else
                        —
                        @endif
                    </td>
                    <td class="text-right text-warning">{{ number_format($cod) }}</td>
                    <td class="text-right font-weight-bold {{ $s->net_payout >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format(abs($s->net_payout)) }} ៛
                        @if($s->net_payout < 0) <small class="text-danger">(Owed)</small>@endif
                    </td>
                    <td class="text-center">
                        @php
                            $cls = match($s->status) {
                                'draft'      => 'secondary',
                                'pending'    => 'warning',
                                'approved'   => 'info',
                                'processing' => 'primary',
                                'paid'       => 'success',
                                'failed'     => 'danger',
                                'cancelled'  => 'dark',
                                default      => 'secondary'
                            };
                        @endphp
                        <span class="status-pill tone-{{ ['secondary' => 'muted', 'dark' => 'muted'][$cls] ?? $cls }}">{{ ucfirst($s->status) }}</span>
                    </td>
                    <td class="text-muted">{{ \Carbon\Carbon::parse($s->created_at)->format('d M Y') }}</td>
                    <td class="text-center">
                        <div class="action-group"><a href="{{ route('admin.settlements.show', $s->id) }}" class="btn btn-sm btn-outline-primary btn-icon" title="Open" data-toggle="tooltip"><i class="fas fa-eye"></i></a></div>
                    </td>
                </tr>
                @empty
                <x-admin.empty-row colspan="12" icon="fa-file-invoice-dollar" title="No settlements yet"
                                   hint="Use Generate Settlement to create payouts for a period." :clear="route('admin.settlements.index')" />
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    </form>
    <x-admin.pagination :paginator="$settlements" />
</div>
@endsection
@push('scripts')
<script>
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = this.checked);
});
function submitBulk(url) {
    var checked = document.querySelectorAll('input[name="ids[]"]:checked');
    if (checked.length === 0) {
        alert('Please select at least one settlement first.');
        return;
    }
    if (!confirm('Apply action to ' + checked.length + ' selected settlement(s)?')) return;
    var form = document.getElementById('bulkForm');
    form.action = url;
    form.submit();
}
</script>
@endpush
