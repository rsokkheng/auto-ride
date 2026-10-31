@extends('admin.layout')
@section('title', 'Top-up Requests')
@section('page-title', 'Top-up Requests')

@section('content')

<x-admin.page-toolbar subtitle="Drivers asking to add money to their wallet. Approve once the payment has arrived." />

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Waiting for Review" :value="number_format($pending->count())" icon="fa-hourglass-half" tone="warning" sub="requests below" />
    <x-admin.stat-card label="Pending Amount" :value="number_format($stats['pending_amount']) . ' ៛'" icon="fa-wallet" tone="info" />
    <x-admin.stat-card label="Approved This Month" :value="number_format($stats['approved_month']) . ' ៛'" icon="fa-circle-check" tone="success"
                       :sub="$stats['approved_count'] . ' ' . Str::plural('request', $stats['approved_count'])" :href="route('admin.topups', ['status' => 'approved'])" :active="request('status') === 'approved'" />
    <x-admin.stat-card label="Rejected This Month" :value="number_format($stats['rejected_month'])" icon="fa-circle-xmark" tone="danger"
                       :href="route('admin.topups', ['status' => 'rejected'])" :active="request('status') === 'rejected'" />
</x-admin.stat-grid>

@php
    $methodTone = ['cash' => 'secondary', 'online' => 'info', 'company_credit' => 'primary'];
    $initials   = fn (?string $name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '?';
@endphp

{{-- Pending queue --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0"><i class="fas fa-hourglass-half mr-2" style="color:var(--warning);"></i>Waiting for review</h3>
        <span class="badge badge-warning">{{ $pending->count() }}</span>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr><th>Driver</th><th class="text-right">Amount</th><th>Method</th><th>Note</th><th>Requested</th><th class="text-center">Actions</th></tr>
            </thead>
            <tbody>
            @forelse($pending as $t)
                <tr>
                    <td>
                        <div class="person">
                            <span class="avatar-chip">{{ $initials($t->user->name) }}</span>
                            <div><div class="person-name">{{ $t->user->name }}</div><div class="person-sub">{{ $t->user->phone ?? $t->user->email }}</div></div>
                        </div>
                    </td>
                    <td class="text-right" style="font-weight:700;color:var(--ink-900);">{{ number_format($t->amount) }} ៛</td>
                    <td><span class="badge badge-{{ $methodTone[$t->method] ?? 'secondary' }}">{{ \App\Models\TopUpRequest::$methodLabels[$t->method] ?? $t->method }}</span></td>
                    <td><div class="text-truncate" style="max-width:220px;" title="{{ $t->note }}">{{ $t->note ?? '—' }}</div></td>
                    <td>
                        <div>{{ $t->created_at->format('d M Y, H:i') }}</div>
                        <div class="text-muted tw:text-xs">{{ $t->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="text-center">
                        <div class="action-group">
                            <form method="POST" action="{{ route('admin.topups.approve', $t) }}" class="m-0">
                                @csrf
                                <button class="btn btn-sm btn-success" onclick="return confirm({{ Illuminate\Support\Js::from('Approve ' . number_format($t->amount) . ' ៛ top-up for ' . $t->user->name . '?') }})">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </button>
                            </form>
                            <button class="btn btn-sm btn-outline-danger" onclick="openReject({{ $t->id }}, {{ Illuminate\Support\Js::from($t->user->name) }})">
                                <i class="fas fa-xmark mr-1"></i>Reject
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state py-4"><div class="empty-icon"><i class="fas fa-check"></i></div><h6>All caught up</h6><p class="mb-0 tw:text-sm">No top-up requests are waiting.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- History --}}
<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.topups')" placeholder="Search driver name, phone or email" :count="$history->total()" noun="processed request">
            <x-admin.filter-select name="status" :options="['approved' => 'Approved', 'rejected' => 'Rejected']" all="Approved & rejected" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr><th>Driver</th><th class="text-right">Amount</th><th>Method</th><th>Status</th><th>Processed</th></tr>
            </thead>
            <tbody>
            @forelse($history as $t)
                <tr>
                    <td>
                        <div class="person">
                            <span class="avatar-chip">{{ $initials($t->user->name) }}</span>
                            <div><div class="person-name">{{ $t->user->name }}</div><div class="person-sub">{{ $t->user->phone ?? $t->user->email }}</div></div>
                        </div>
                    </td>
                    <td class="text-right" style="font-weight:600;color:var(--ink-900);">{{ number_format($t->amount) }} ៛</td>
                    <td>{{ \App\Models\TopUpRequest::$methodLabels[$t->method] ?? $t->method }}</td>
                    <td><span class="status-pill tone-{{ $t->status === 'approved' ? 'success' : 'danger' }}">{{ ucfirst($t->status) }}</span></td>
                    <td>
                        <div>{{ ($t->approved_at ?? $t->updated_at)?->format('d M Y, H:i') }}</div>
                        <div class="text-muted tw:text-xs">by {{ $t->approvedBy?->name ?? '—' }}</div>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="5" icon="fa-clock-rotate-left" title="No processed requests yet" :clear="route('admin.topups')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$history" />
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectTitle">Reject Request</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="rejectForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Reason <small class="text-muted">(optional)</small></label>
                        <textarea name="admin_note" class="form-control" rows="3" placeholder="Reason for rejection…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openReject(id, name) {
    document.getElementById('rejectTitle').textContent = 'Reject request for ' + name;
    document.getElementById('rejectForm').action = '/admin/topups/' + id + '/reject';
    $('#rejectModal').modal('show');
}
</script>
@endpush
