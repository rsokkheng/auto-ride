@extends('admin.layout')
@section('title', 'Transaction Records')
@section('page-title', 'Transaction Records')

@section('content')

{{-- Summary alerts --}}
@if($pending_cash > 0)
<div class="alert alert-warning alert-dismissible fade show">
    <i class="fas fa-money-bill-wave mr-2"></i>
    <strong>{{ $pending_cash }}</strong> cash payment(s) waiting for confirmation.
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif
@if($pending_online > 0)
<div class="alert alert-info alert-dismissible fade show">
    <i class="fas fa-university mr-2"></i>
    <strong>{{ $pending_online }}</strong> online payment(s) (ABA / Wing) waiting for confirmation.
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.transactions') }}" class="form-inline">
            <select name="method" class="form-control form-control-sm mr-2">
                <option value="">All Methods</option>
                @foreach(\App\Models\TransactionRecord::$methodLabels as $val => $label)
                    <option value="{{ $val }}" {{ request('method') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">All Statuses</option>
                <option value="pending"   {{ request('status') == 'pending'   ? 'selected' : '' }}>Pending</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            <select name="type" class="form-control form-control-sm mr-2">
                <option value="">All Types</option>
                @foreach(\App\Models\TransactionRecord::$typeLabels as $val => $label)
                    <option value="{{ $val }}" {{ request('type') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter mr-1"></i>Filter</button>
            <a href="{{ route('admin.transactions') }}" class="btn btn-sm btn-secondary">Reset</a>
        </form>
    </div>
</div>

{{-- Transaction table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-receipt mr-1"></i> All Transactions</h3>
        <span class="badge badge-secondary">{{ $transactions->total() }} records</span>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reference</th>
                    <th>Payer</th>
                    <th>Driver</th>
                    <th>Method</th>
                    <th>Paid By</th>
                    <th>Total (KHR)</th>
                    <th>Platform Fee</th>
                    <th>Driver Earns</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                @php
                    $icon  = \App\Models\TransactionRecord::$methodIcons[$tx->payment_method]  ?? 'fa-question';
                    $color = \App\Models\TransactionRecord::$methodColors[$tx->payment_method] ?? 'secondary';
                    $sColor = \App\Models\TransactionRecord::$statusColors[$tx->status] ?? 'secondary';
                @endphp
                <tr>
                    <td>{{ $tx->id }}</td>
                    <td>
                        <span class="badge badge-light">
                            {{ \App\Models\TransactionRecord::$typeLabels[$tx->type] ?? $tx->type }}
                        </span>
                        <small class="text-muted d-block">
                            {{ class_basename($tx->reference_type ?? '') }} #{{ $tx->reference_id }}
                        </small>
                    </td>
                    <td>{{ $tx->payer?->name ?? '—' }}</td>
                    <td>{{ $tx->payee?->name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $color }}">
                            <i class="fas {{ $icon }} mr-1"></i>
                            {{ \App\Models\TransactionRecord::$methodLabels[$tx->payment_method] ?? $tx->payment_method }}
                        </span>
                    </td>
                    <td>
                        @if($tx->payment_by === 'recipient')
                            <span class="badge badge-warning">Recipient (COD)</span>
                        @else
                            <span class="badge badge-info">Sender</span>
                        @endif
                    </td>
                    <td><strong>{{ number_format($tx->gross_amount, 0) }} ៛</strong></td>
                    <td class="text-danger">{{ number_format($tx->platform_fee, 0) }} ៛</td>
                    <td class="text-success"><strong>{{ number_format($tx->driver_earning, 0) }} ៛</strong></td>
                    <td>
                        <span class="badge badge-{{ $sColor }}">{{ ucfirst($tx->status) }}</span>
                        @if($tx->processedBy)
                            <small class="text-muted d-block">by {{ $tx->processedBy->name }}</small>
                        @endif
                    </td>
                    <td>{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        @if($tx->isPending())
                            <form method="POST" action="{{ route('admin.transactions.confirm', $tx) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-xs btn-success"
                                    onclick="return confirm('Confirm payment of {{ number_format($tx->gross_amount, 0) }} ៛?')">
                                    <i class="fas fa-check"></i> Confirm
                                </button>
                            </form>
                            <button class="btn btn-xs btn-danger ml-1"
                                onclick="openCancel({{ $tx->id }})">
                                <i class="fas fa-times"></i>
                            </button>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" class="text-center text-muted py-4">No transactions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $transactions->links() }}</div>
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
