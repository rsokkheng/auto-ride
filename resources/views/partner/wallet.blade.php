@extends('partner.layout')
@section('title', 'Wallet & COD')
@section('page-title', 'Wallet & COD')

@section('content')

{{-- ── Balance Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="rounded-2xl p-6 text-white bg-gradient-to-br from-emerald-500 to-emerald-700 shadow-sm">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                <i class="fas fa-wallet text-lg"></i>
            </div>
            <span class="text-sm font-medium opacity-90">Wallet Balance</span>
        </div>
        <div class="text-3xl font-extrabold leading-none">{{ number_format($partner->wallet_balance) }} ៛</div>
        <div class="text-emerald-100 text-xs mt-1.5">≈ ${{ number_format($partner->wallet_balance / 4000, 2) }}</div>
    </div>

    <div class="rounded-2xl p-6 text-white bg-gradient-to-br from-amber-400 to-amber-600 shadow-sm">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                <i class="fas fa-hand-holding-usd text-lg"></i>
            </div>
            <span class="text-sm font-medium opacity-90">Pending COD</span>
        </div>
        <div class="text-3xl font-extrabold leading-none">{{ number_format($codTotal) }} ៛</div>
        <div class="text-amber-100 text-xs mt-1.5">Unpaid deliveries</div>
    </div>

    <div class="rounded-2xl p-6 text-white bg-gradient-to-br from-blue-500 to-blue-700 shadow-sm">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                <i class="fas fa-check-circle text-lg"></i>
            </div>
            <span class="text-sm font-medium opacity-90">COD Collected</span>
        </div>
        <div class="text-3xl font-extrabold leading-none">{{ number_format($codCollected) }} ៛</div>
        <div class="text-blue-100 text-xs mt-1.5">Paid deliveries</div>
    </div>
</div>

<div class="row g-4">

    {{-- ── Top-up History ───────────────────────────────────────── --}}
    <div class="col-md-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden h-100">
            <div class="flex items-center gap-2 px-5 py-4 border-b border-slate-100">
                <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <i class="fas fa-arrow-up text-emerald-600 text-xs"></i>
                </div>
                <h3 class="font-semibold text-slate-800 mb-0">Top-up Requests</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse($topups as $t)
                        @php $bc = ['pending'=>'warning','approved'=>'success','rejected'=>'danger']; @endphp
                        <tr>
                            <td class="text-muted small">{{ $t->id }}</td>
                            <td><strong>{{ number_format($t->amount_khr) }} ៛</strong></td>
                            <td><small>{{ $t->payment_method ?? '—' }}</small></td>
                            <td><span class="badge bg-{{ $bc[$t->status] ?? 'secondary' }}">{{ ucfirst($t->status) }}</span></td>
                            <td><small class="text-muted">{{ $t->created_at->format('d M H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-8">
                                <i class="fas fa-arrow-up fa-2x text-slate-200 mb-2 d-block"></i>
                                <p class="text-slate-400 small mb-0">No top-up requests yet.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($topups->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $topups->links() }}</div>
            @endif
        </div>
    </div>

    {{-- ── Withdrawal History ───────────────────────────────────── --}}
    <div class="col-md-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden h-100">
            <div class="flex items-center gap-2 px-5 py-4 border-b border-slate-100">
                <div class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center">
                    <i class="fas fa-arrow-down text-red-600 text-xs"></i>
                </div>
                <h3 class="font-semibold text-slate-800 mb-0">Withdrawal Requests</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Amount</th><th>Account</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse($withdrawals as $w)
                        @php $bc = ['pending'=>'warning','approved'=>'success','rejected'=>'danger']; @endphp
                        <tr>
                            <td class="text-muted small">{{ $w->id }}</td>
                            <td><strong>{{ number_format($w->amount_khr) }} ៛</strong></td>
                            <td><small>{{ $w->bank_name ?? $w->payment_method ?? '—' }}</small></td>
                            <td><span class="badge bg-{{ $bc[$w->status] ?? 'secondary' }}">{{ ucfirst($w->status) }}</span></td>
                            <td><small class="text-muted">{{ $w->created_at->format('d M H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-8">
                                <i class="fas fa-arrow-down fa-2x text-slate-200 mb-2 d-block"></i>
                                <p class="text-slate-400 small mb-0">No withdrawal requests yet.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($withdrawals->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $withdrawals->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection
