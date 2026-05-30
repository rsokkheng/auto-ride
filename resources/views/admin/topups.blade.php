@extends('admin.layout')
@section('title', 'Top-up Requests')
@section('page-title', 'Top-up Requests')

@section('content')

{{-- Pending --}}
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="fas fa-clock text-warning mr-1"></i>
            Pending Requests
            @if($pending->count())
                <span class="badge badge-warning ml-1">{{ $pending->count() }}</span>
            @endif
        </h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Driver</th><th>Amount</th><th>Method</th><th>Note</th><th>Requested</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pending as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>
                        <strong>{{ $t->user->name }}</strong><br>
                        <small class="text-muted">{{ $t->user->phone ?? $t->user->email }}</small>
                    </td>
                    <td><strong>{{ number_format($t->amount, 0) }} ៛</strong></td>
                    <td>
                        @php $mc = ['cash'=>'secondary','online'=>'info','company_credit'=>'primary']; @endphp
                        <span class="badge badge-{{ $mc[$t->method] ?? 'secondary' }}">
                            {{ \App\Models\TopUpRequest::$methodLabels[$t->method] ?? $t->method }}
                        </span>
                    </td>
                    <td>{{ $t->note ?? '—' }}</td>
                    <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.topups.approve', $t) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-xs btn-success" onclick="return confirm('Approve {{ number_format($t->amount,0) }} ៛ top-up for {{ addslashes($t->user->name) }}?')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <button class="btn btn-xs btn-danger ml-1"
                            onclick="openReject({{ $t->id }}, '{{ addslashes($t->user->name) }}')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No pending requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- History --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="fas fa-history mr-1"></i> Processed History</h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Driver</th><th>Amount</th><th>Method</th><th>Status</th><th>Processed by</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $t)
                @php $sc = ['approved'=>'success','rejected'=>'danger']; @endphp
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>{{ $t->user->name }}</td>
                    <td>{{ number_format($t->amount, 0) }} ៛</td>
                    <td>{{ \App\Models\TopUpRequest::$methodLabels[$t->method] ?? $t->method }}</td>
                    <td>
                        <span class="badge badge-{{ $sc[$t->status] ?? 'secondary' }}">
                            {{ ucfirst($t->status) }}
                        </span>
                    </td>
                    <td>{{ $t->approvedBy?->name ?? '—' }}</td>
                    <td>{{ $t->approved_at?->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No history.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $history->links() }}</div>
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
