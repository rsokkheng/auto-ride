@extends('admin.layout')
@section('title', 'Payment Settlements')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-file-invoice-dollar mr-2 text-success"></i>Payment Settlements</h1></div>
    <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">Settlements</li>
        </ol>
    </div>
</div>
@endsection
@section('content')
<div class="container-fluid">

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
@endif

{{-- Summary Cards --}}
<div class="row">
    <div class="col-lg-2 col-md-4 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-secondary"><i class="fas fa-list"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total</span>
                <span class="info-box-number">{{ number_format($summary->total ?? 0) }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pending</span>
                <span class="info-box-number">{{ number_format($summary->pending_count ?? 0) }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-thumbs-up"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Approved</span>
                <span class="info-box-number">{{ number_format($summary->approved_count ?? 0) }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-primary"><i class="fas fa-spinner"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Processing</span>
                <span class="info-box-number">{{ number_format($summary->processing_count ?? 0) }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-check-double"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Paid</span>
                <span class="info-box-number" style="font-size:.85rem;">{{ number_format($summary->total_paid ?? 0) }} ៛</span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-danger"><i class="fas fa-hourglass-half"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pending Amount</span>
                <span class="info-box-number" style="font-size:.85rem;">{{ number_format($summary->total_pending_amount ?? 0) }} ៛</span>
            </div>
        </div>
    </div>
</div>

{{-- Filters + Actions --}}
<div class="card card-outline card-success mb-3">
    <div class="card-body py-2">
        <form method="GET" class="form-inline flex-wrap gap-2">
            <select name="type" class="form-control form-control-sm mr-2">
                <option value="">All Types</option>
                <option value="driver" {{ request('type')=='driver'?'selected':'' }}>Driver</option>
                <option value="partner" {{ request('type')=='partner'?'selected':'' }}>Partner</option>
            </select>
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">All Status</option>
                @foreach(['draft','pending','approved','processing','paid','failed','cancelled'] as $st)
                <option value="{{ $st }}" {{ request('status')==$st?'selected':'' }}>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Name..." value="{{ request('search') }}">
            <input type="date" name="from" class="form-control form-control-sm mr-2" value="{{ request('from') }}">
            <input type="date" name="to" class="form-control form-control-sm mr-2" value="{{ request('to') }}">
            <button type="submit" class="btn btn-sm btn-success mr-2"><i class="fas fa-search mr-1"></i>Filter</button>
            <a href="{{ route('admin.settlements.index') }}" class="btn btn-sm btn-outline-secondary mr-3">Reset</a>
            <a href="{{ route('admin.settlements.export', request()->query()) }}" class="btn btn-sm btn-success ml-auto mr-1">
                <i class="fas fa-file-excel mr-1"></i>Export Excel
            </a>
            <a href="{{ route('admin.settlements.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus mr-1"></i>Generate Settlement
            </a>
        </form>
    </div>
</div>

{{-- Bulk actions --}}
<form id="bulkForm" method="POST">
@csrf
<div class="card card-outline card-success">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fas fa-list mr-2"></i>Settlements</h3>
        <div class="ml-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-warning mr-1" onclick="submitBulk('{{ route('admin.settlements.bulk-approve') }}')">
                <i class="fas fa-thumbs-up mr-1"></i>Bulk Approve
            </button>
            <button type="button" class="btn btn-sm btn-primary" onclick="submitBulk('{{ route('admin.settlements.bulk-process') }}')">
                <i class="fas fa-play mr-1"></i>Bulk Process
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-dark">
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="checkAll"></th>
                    <th>#</th>
                    <th>Type</th>
                    <th>Entity</th>
                    <th>Period</th>
                    <th class="text-right">Gross</th>
                    <th class="text-right">Commission</th>
                    <th class="text-right">COD</th>
                    <th class="text-right">Net Payout</th>
                    <th class="text-center">Status</th>
                    <th>Created</th>
                    <th>Actions</th>
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
                        <strong>{{ $s->entity_name }}</strong>
                        <div class="text-muted" style="font-size:.75rem;">{{ $s->entity_phone }}</div>
                    </td>
                    <td>
                        <small>{{ \Carbon\Carbon::parse($s->period_start)->format('d M Y') }}</small>
                        <small class="text-muted"> – </small>
                        <small>{{ \Carbon\Carbon::parse($s->period_end)->format('d M Y') }}</small>
                    </td>
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
                        <span class="badge badge-{{ $cls }}">{{ ucfirst($s->status) }}</span>
                    </td>
                    <td><small class="text-muted">{{ \Carbon\Carbon::parse($s->created_at)->format('d M Y') }}</small></td>
                    <td>
                        <a href="{{ route('admin.settlements.show', $s->id) }}" class="btn btn-xs btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" class="text-center py-5 text-muted">No settlements found. <a href="{{ route('admin.settlements.create') }}">Generate one now</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @if($settlements->hasPages())
    <div class="card-footer">{{ $settlements->links('pagination::bootstrap-4') }}</div>
    @endif
</div>
</form>

</div>
@endsection
@push('scripts')
<script>
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = this.checked);
});
function submitBulk(url) {
    var form = document.getElementById('bulkForm');
    form.action = url;
    form.submit();
}
</script>
@endpush
