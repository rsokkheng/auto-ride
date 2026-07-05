@extends('admin.layout')
@section('title', 'Withdrawal Requests')
@section('page-title', 'Withdrawal Requests')

@section('content')

{{-- Flash messages --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<div class="card">
    <div class="card-header p-0 pt-1">
        <ul class="nav nav-tabs">
            @foreach(['pending' => ['warning','clock'], 'approved' => ['success','check-circle'], 'rejected' => ['danger','times-circle']] as $tab => [$color, $icon])
            <li class="nav-item">
                <a class="nav-link {{ $status === $tab ? 'active' : '' }}"
                   href="{{ route('admin.withdrawals', ['status' => $tab]) }}">
                    <i class="fas fa-{{ $icon }} text-{{ $color }} mr-1"></i>
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
                    <th>Account Details</th>
                    <th>Requested</th>
                    @if($status !== 'pending')
                        <th>Processed By</th>
                        <th>Admin Note</th>
                    @endif
                    @if($status === 'pending')
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $w)
                <tr>
                    <td class="text-muted small">{{ $w->id }}</td>
                    <td>
                        <div class="font-weight-bold">{{ $w->driver->name }}</div>
                        <small class="text-muted">{{ $w->driver->phone }}</small>
                        <div>
                            <small class="badge badge-light text-dark">
                                Balance: {{ number_format($w->driver->wallet_balance) }} ៛
                            </small>
                        </div>
                    </td>
                    <td>
                        <span class="font-weight-bold text-success" style="font-size:1rem">
                            {{ number_format($w->amount_khr) }} ៛
                        </span>
                        <div><small class="text-muted">≈ ${{ number_format($w->amount_khr / 4000, 2) }}</small></div>
                    </td>
                    <td>
                        <span class="badge badge-secondary">
                            {{ str_replace('_', ' ', strtoupper($w->payment_method)) }}
                        </span>
                    </td>
                    <td>
                        <div class="font-weight-bold">{{ $w->account_name ?? '—' }}</div>
                        <small class="text-muted">{{ $w->account_number ?? '—' }}</small>
                        @if($w->bank_name)
                            <div><small class="text-muted"><i class="fas fa-university mr-1"></i>{{ $w->bank_name }}</small></div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $w->created_at->format('d M Y') }}</div>
                        <small class="text-muted">{{ $w->created_at->format('H:i') }}</small>
                    </td>
                    @if($status !== 'pending')
                        <td>
                            @if($w->processor)
                                <div>{{ $w->processor->name }}</div>
                                <small class="text-muted">{{ $w->processed_at?->format('d M Y H:i') }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $w->admin_note ?? '—' }}</small></td>
                    @endif
                    @if($status === 'pending')
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-success mr-1"
                                onclick="openApprove({{ $w->id }}, '{{ e($w->driver->name) }}', {{ $w->amount_khr }})">
                                <i class="fas fa-check mr-1"></i>Approve
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="openReject({{ $w->id }}, '{{ e($w->driver->name) }}')">
                                <i class="fas fa-times mr-1"></i>Reject
                            </button>
                        </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        No {{ $status }} withdrawal requests.
                    </td>
                </tr>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle mr-2"></i>Approve Withdrawal</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p id="approveText" class="mb-3"></p>
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        The driver's wallet has already been debited. Approving marks this request as paid out.
                    </p>
                    <div class="form-group mb-0">
                        <label>Reference / Note <small class="text-muted">(optional)</small></label>
                        <input type="text" name="admin_note" class="form-control"
                               placeholder="Transaction reference, receipt number, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i>Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Reject Withdrawal</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p id="rejectText" class="mb-3"></p>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-undo mr-1"></i>
                        The held amount will be <strong>returned to the driver's wallet</strong>.
                    </div>
                    <div class="form-group mb-0">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea name="admin_note" class="form-control" rows="2" required
                                  placeholder="Why is this being rejected?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times mr-1"></i>Reject &amp; Refund
                    </button>
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
    document.getElementById('approveText').innerHTML =
        'Approve <strong>' + number_format(amount) + ' ៛</strong> withdrawal for <strong>' + name + '</strong>?';
    $('#approveModal').modal('show');
}

function openReject(id, name) {
    document.getElementById('rejectForm').action = '/admin/withdrawals/' + id + '/reject';
    document.getElementById('rejectText').innerHTML =
        'Reject withdrawal request from <strong>' + name + '</strong>?';
    $('#rejectModal').modal('show');
}

function number_format(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>
@endpush
