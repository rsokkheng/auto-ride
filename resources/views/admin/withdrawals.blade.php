@extends('admin.layout')
@section('title', 'Withdrawal Requests')
@section('page-title', 'Withdrawal Requests')

@section('content')
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            @foreach(['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'] as $tab => $color)
            <li class="nav-item">
                <a class="nav-link {{ $status === $tab ? 'active' : '' }}" href="{{ route('admin.withdrawals', ['status' => $tab]) }}">
                    {{ ucfirst($tab) }}
                    @if($counts[$tab] > 0)
                        <span class="badge badge-{{ $color }} ml-1">{{ $counts[$tab] }}</span>
                    @endif
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Driver</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account</th>
                    <th>Requested</th>
                    @if($status !== 'pending')<th>Processed</th>@endif
                    <th>Note</th>
                    @if($status === 'pending')<th>Actions</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $w)
                <tr>
                    <td>{{ $w->id }}</td>
                    <td>
                        <div class="font-weight-bold">{{ $w->driver->name }}</div>
                        <small class="text-muted">{{ $w->driver->phone }}</small>
                        <div><small class="text-info">Balance: {{ number_format($w->driver->wallet_balance) }} ៛</small></div>
                    </td>
                    <td><span class="font-weight-bold text-success">{{ number_format($w->amount_khr) }} ៛</span></td>
                    <td><span class="badge badge-secondary">{{ str_replace('_',' ', strtoupper($w->payment_method)) }}</span></td>
                    <td>
                        <div>{{ $w->account_name }}</div>
                        <small class="text-muted">{{ $w->account_number }}</small>
                        @if($w->bank_name)<div><small class="text-muted">{{ $w->bank_name }}</small></div>@endif
                    </td>
                    <td>{{ $w->created_at->format('d M Y H:i') }}</td>
                    @if($status !== 'pending')
                    <td>{{ $w->processed_at?->format('d M Y H:i') ?? '—' }}</td>
                    @endif
                    <td><small class="text-muted">{{ $w->admin_note ?? '—' }}</small></td>
                    @if($status === 'pending')
                    <td class="text-nowrap">
                        <button class="btn btn-xs btn-success mr-1"
                            onclick="openApprove({{ $w->id }}, '{{ $w->driver->name }}', {{ $w->amount_khr }})">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-xs btn-danger"
                            onclick="openReject({{ $w->id }}, '{{ $w->driver->name }}')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No {{ $status }} withdrawal requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($withdrawals->hasPages())
    <div class="card-footer">{{ $withdrawals->appends(['status' => $status])->links() }}</div>
    @endif
</div>

{{-- Approve modal --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Approve Withdrawal</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p id="approveText" class="mb-2"></p>
                    <div class="form-group mb-0">
                        <label>Note (optional)</label>
                        <input type="text" name="admin_note" class="form-control" placeholder="Transaction reference, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Withdrawal</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p id="rejectText" class="mb-2"></p>
                    <div class="form-group mb-0">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea name="admin_note" class="form-control" rows="2" required placeholder="Why is this being rejected?"></textarea>
                    </div>
                    <small class="text-muted">Funds will be returned to the driver's wallet.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times mr-1"></i> Reject &amp; Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openApprove(id, name, amount) {
    document.getElementById('approveForm').action = '/admin/withdrawals/' + id + '/approve';
    document.getElementById('approveText').innerHTML = 'Approve <strong>' + (amount/1000).toFixed(0) + 'K ៛</strong> for <strong>' + name + '</strong>?';
    $('#approveModal').modal('show');
}
function openReject(id, name) {
    document.getElementById('rejectForm').action = '/admin/withdrawals/' + id + '/reject';
    document.getElementById('rejectText').innerHTML = 'Reject withdrawal for <strong>' + name + '</strong>?';
    $('#rejectModal').modal('show');
}
</script>
@endpush
