@extends('partner.layout')
@section('title', 'Wallet & COD')
@section('page-title', 'Wallet & COD')

@section('content')

{{-- Balance Cards --}}
<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card text-center p-4" style="background:linear-gradient(135deg,#10b981,#059669);border-radius:12px;color:#fff">
            <div style="font-size:2.5rem"><i class="fas fa-wallet"></i></div>
            <div class="h3 font-weight-bold mt-2 mb-0">{{ number_format($partner->wallet_balance) }} ៛</div>
            <div class="small mt-1" style="opacity:.8">≈ ${{ number_format($partner->wallet_balance / 4000, 2) }}</div>
            <div class="small mt-1" style="opacity:.7">Wallet Balance</div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-center p-4" style="background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:12px;color:#fff">
            <div style="font-size:2.5rem"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="h3 font-weight-bold mt-2 mb-0">{{ number_format($codTotal) }} ៛</div>
            <div class="small mt-1" style="opacity:.7">Pending COD (unpaid)</div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-center p-4" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:12px;color:#fff">
            <div style="font-size:2.5rem"><i class="fas fa-check-circle"></i></div>
            <div class="h3 font-weight-bold mt-2 mb-0">{{ number_format($codCollected) }} ៛</div>
            <div class="small mt-1" style="opacity:.7">COD Collected (paid)</div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Top-up History --}}
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-arrow-up text-success mr-2"></i>Top-up Requests</h5></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr><th>#</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse($topups as $t)
                        <tr>
                            <td class="text-muted small">{{ $t->id }}</td>
                            <td><strong>{{ number_format($t->amount_khr) }} ៛</strong></td>
                            <td><small>{{ $t->payment_method ?? '—' }}</small></td>
                            <td>
                                @php $bc = ['pending'=>'warning','approved'=>'success','rejected'=>'danger']; @endphp
                                <span class="badge badge-{{ $bc[$t->status] ?? 'secondary' }}">{{ ucfirst($t->status) }}</span>
                            </td>
                            <td><small class="text-muted">{{ $t->created_at->format('d M H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No top-up requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($topups->hasPages())
            <div class="card-footer py-2">{{ $topups->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Withdrawal History --}}
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-arrow-down text-danger mr-2"></i>Withdrawal Requests</h5></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr><th>#</th><th>Amount</th><th>Account</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse($withdrawals as $w)
                        <tr>
                            <td class="text-muted small">{{ $w->id }}</td>
                            <td><strong>{{ number_format($w->amount_khr) }} ៛</strong></td>
                            <td><small>{{ $w->bank_name ?? $w->payment_method ?? '—' }}</small></td>
                            <td>
                                @php $bc = ['pending'=>'warning','approved'=>'success','rejected'=>'danger']; @endphp
                                <span class="badge badge-{{ $bc[$w->status] ?? 'secondary' }}">{{ ucfirst($w->status) }}</span>
                            </td>
                            <td><small class="text-muted">{{ $w->created_at->format('d M H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No withdrawal requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($withdrawals->hasPages())
            <div class="card-footer py-2">{{ $withdrawals->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
