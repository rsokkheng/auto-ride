@extends('admin.layout')
@section('title', 'Transaction Records')
@section('page-title', 'Transaction Records')

@section('content')

<x-admin.page-toolbar subtitle="Every payment for rides, deliveries and moving jobs, with the platform's fee and the driver's share." />

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Total Collected" :value="number_format($stats->gross) . ' ៛'" icon="fa-sack-dollar" tone="primary"
                       :sub="number_format($stats->n) . ' completed payments'" :href="route('admin.transactions', ['status' => 'completed'])" :active="request('status') === 'completed'" />
    <x-admin.stat-card label="Platform Fees" :value="number_format($stats->fee) . ' ៛'" icon="fa-building-columns" tone="violet"
                       :sub="$stats->gross ? round($stats->fee / $stats->gross * 100, 1) . '% of collected' : '—'" />
    <x-admin.stat-card label="Paid to Drivers" :value="number_format($stats->driver) . ' ៛'" icon="fa-hand-holding-dollar" tone="success" />
    <x-admin.stat-card label="Awaiting Confirmation" :value="number_format($pending_total)" icon="fa-hourglass-half" tone="warning"
                       :sub="$pending_cash . ' cash · ' . $pending_online . ' online'" :href="route('admin.transactions', ['status' => 'pending'])" :active="request('status') === 'pending'" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.transactions')" placeholder="Search #, payer or driver name, phone, email" :count="$transactions->total()" noun="transaction">
            <x-admin.filter-select name="status" :options="['pending' => 'Pending', 'completed' => 'Completed', 'cancelled' => 'Cancelled']" all="All statuses" />
            <x-admin.filter-select name="method" :options="\App\Models\TransactionRecord::$methodLabels" all="All methods" />
            <x-admin.filter-select name="type" :options="\App\Models\TransactionRecord::$typeLabels" all="All types" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Transaction</th>
                    <th>Payer → Driver</th>
                    <th>Method</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Fee / Driver</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
                @php
                    $icon   = \App\Models\TransactionRecord::$methodIcons[$tx->payment_method] ?? 'fa-question';
                    $sTone  = ['pending' => 'warning', 'completed' => 'success', 'cancelled' => 'muted'][$tx->status] ?? 'muted';
                    [$srcLabel, $srcIcon, $srcTone] = match (true) {
                        $tx->type === 'ride_payment' => ['Ride', 'fa-car-side', 'primary'],
                        $tx->type === 'delivery_payment' && optional($tx->reference)->service_type === 'moving' => ['Moving', 'fa-truck', 'warning'],
                        $tx->type === 'delivery_payment' => ['Delivery', 'fa-box', 'info'],
                        default => [\App\Models\TransactionRecord::$typeLabels[$tx->type] ?? $tx->type, 'fa-receipt', 'muted'],
                    };
                @endphp
                <tr id="row-{{ $tx->id }}">
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <span class="stat-icon tone-{{ $srcTone }}" style="width:36px;height:36px;font-size:.85rem;"><i class="fas {{ $srcIcon }}"></i></span>
                            <div>
                                <div style="font-weight:600;color:var(--ink-900);">#{{ $tx->id }} · {{ $srcLabel }} #{{ $tx->reference_id }}</div>
                                <div class="text-muted tw:text-xs">{{ $tx->created_at->format('d M Y, H:i') }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>{{ $tx->payer?->name ?? '—' }} <i class="fas fa-arrow-right mx-1 text-muted" style="font-size:.65rem;"></i> {{ $tx->payee?->name ?? '—' }}</div>
                        <div class="text-muted tw:text-xs">{{ $tx->payment_by === 'recipient' ? 'Paid by recipient (COD)' : 'Paid by sender' }}</div>
                    </td>
                    <td><span class="badge badge-light"><i class="fas {{ $icon }} mr-1"></i>{{ \App\Models\TransactionRecord::$methodLabels[$tx->payment_method] ?? $tx->payment_method }}</span></td>
                    <td class="text-right" style="font-weight:600;color:var(--ink-900);">{{ number_format($tx->gross_amount) }} ៛</td>
                    <td class="text-right">
                        <div style="color:var(--danger);">−{{ number_format($tx->platform_fee) }} ៛</div>
                        <div style="color:var(--success);font-weight:600;">{{ number_format($tx->driver_earning) }} ៛</div>
                    </td>
                    <td>
                        <span class="status-pill tone-{{ $sTone }}">{{ ucfirst($tx->status) }}</span>
                        @if($tx->processedBy)<div class="text-muted tw:text-xs tw:mt-1">by {{ $tx->processedBy->name }}</div>@endif
                    </td>
                    <td class="text-center">
                        @if($tx->isPending())
                            <div class="action-group">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="Cancel transaction" data-toggle="tooltip" onclick="openCancel({{ $tx->id }})">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="7" icon="fa-receipt" title="No transactions yet" :clear="route('admin.transactions')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$transactions" />
</div>

{{-- Cancel modal --}}
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Transaction</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="cancelForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Reason <small class="text-muted">(optional)</small></label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Reason for cancellation…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i>Cancel Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openCancel(id) {
    document.getElementById('cancelForm').action = '/admin/transactions/' + id + '/cancel';
    $('#cancelModal').modal('show');
}
</script>
@endpush
