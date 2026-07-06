@extends('partner.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

{{-- ── Stat Cards ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php
    $statCards = [
        ['Orders Today',       $stats['today'],                   'fas fa-box',         'from-blue-500 to-blue-700'],
        ['Pending / Assigned', $stats['created']+$stats['assigned'], 'fas fa-clock',    'from-amber-400 to-amber-600'],
        ['In Transit',         $stats['in_transit'],              'fas fa-truck',       'from-violet-500 to-violet-700'],
        ['Delivered',          $stats['delivered'],               'fas fa-check-circle','from-emerald-500 to-emerald-700'],
    ];
    @endphp
    @foreach($statCards as [$label, $value, $icon, $grad])
    <div class="rounded-2xl p-5 text-white relative overflow-hidden bg-gradient-to-br {{ $grad }}">
        <i class="{{ $icon }} absolute right-4 top-4 text-3xl opacity-20"></i>
        <div class="text-3xl font-extrabold leading-none">{{ $value }}</div>
        <div class="text-sm mt-1 opacity-85">{{ $label }}</div>
    </div>
    @endforeach
</div>

{{-- ── Secondary stats ─────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php
    $secondaryCards = [
        ['This Week',    $stats['week'],      null,        'text-slate-800'],
        ['This Month',   $stats['month'],     null,        'text-slate-800'],
        ['Cancelled',    $stats['cancelled'], null,        'text-red-600'],
        ['Success Rate', $successRate.'%',   null,        'text-emerald-600'],
    ];
    @endphp
    @foreach($secondaryCards as [$label, $val, $_, $color])
    <div class="bg-white rounded-2xl p-4 text-center shadow-sm border border-slate-100">
        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-1">{{ $label }}</p>
        <p class="text-2xl font-bold {{ $color }}">{{ $val }}</p>
    </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-3 gap-6">

    {{-- ── Active Deliveries ───────────────────────────────────── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-truck text-blue-500"></i> Live Active Deliveries
                </h2>
                <a href="{{ route('partner.orders', ['status' => 'in_transit']) }}"
                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">View all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Recipient</th><th>Status</th><th>Driver</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($active as $d)
                        @php
                        $sc = ['assigned'=>'warning','accepted'=>'info','picked_up'=>'primary','in_transit'=>'secondary'];
                        @endphp
                        <tr>
                            <td class="text-muted small">{{ $d->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $d->recipient_name }}</div>
                                <small class="text-muted">{{ $d->recipient_phone }}</small>
                            </td>
                            <td>
                                <span class="badge bg-{{ $sc[$d->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_',' ',$d->status)) }}
                                </span>
                            </td>
                            <td>
                                @if($d->driver)
                                    <div class="small fw-semibold">{{ $d->driver->name }}</div>
                                    <small class="text-muted">{{ $d->driver->phone }}</small>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('partner.orders.show', $d) }}"
                                   class="btn btn-sm btn-outline-secondary btn-sm py-0 px-2">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No active deliveries right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Right column: Wallet + Quick Actions ────────────────── --}}
    <div class="space-y-4">

        {{-- Wallet --}}
        <div class="rounded-2xl p-5 text-white bg-gradient-to-br from-emerald-500 to-emerald-700 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-wallet text-lg"></i>
                </div>
                <span class="text-sm font-medium opacity-90">Wallet Balance</span>
            </div>
            <div class="text-3xl font-extrabold leading-none">{{ number_format($partner->wallet_balance) }} ៛</div>
            <div class="text-emerald-100 text-xs mt-1">≈ ${{ number_format($partner->wallet_balance / 4000, 2) }}</div>
            <a href="{{ route('partner.wallet') }}"
               class="mt-4 flex items-center justify-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 rounded-xl text-sm font-medium transition-colors">
                <i class="fas fa-history"></i> View Wallet
            </a>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                <i class="fas fa-bolt text-amber-500"></i> Quick Actions
            </h3>
            <div class="space-y-2">
                <a href="{{ route('partner.orders.create') }}"
                   class="flex items-center gap-2 w-full px-4 py-2.5 rounded-xl text-sm font-medium text-white transition-all active:scale-95"
                   style="background:linear-gradient(135deg,#e63946,#c1121f)">
                    <i class="fas fa-plus"></i> New Delivery Order
                </a>
                <a href="{{ route('partner.orders') }}"
                   class="flex items-center gap-2 w-full px-4 py-2.5 rounded-xl text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                    <i class="fas fa-list"></i> All Orders
                </a>
                <a href="{{ route('partner.reports') }}"
                   class="flex items-center gap-2 w-full px-4 py-2.5 rounded-xl text-sm font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 transition-colors">
                    <i class="fas fa-chart-bar"></i> View Reports
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ── Recent Orders ────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mt-6">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="fas fa-history text-slate-400"></i> Recent Orders
        </h2>
        <a href="{{ route('partner.orders') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Ref</th><th>Recipient</th><th>Dropoff</th><th>Fee</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse($recent as $d)
                @php
                $sc = ['created'=>'secondary','assigned'=>'warning','accepted'=>'info','picked_up'=>'primary',
                       'in_transit'=>'dark','delivered'=>'success','completed'=>'success','cancelled'=>'danger'];
                @endphp
                <tr>
                    <td><a href="{{ route('partner.orders.show', $d) }}" class="fw-bold text-decoration-none">#{{ $d->id }}</a></td>
                    <td><small class="text-muted">{{ $d->partner_reference ?? '—' }}</small></td>
                    <td>
                        <div class="fw-semibold">{{ $d->recipient_name }}</div>
                        <small class="text-muted">{{ $d->recipient_phone }}</small>
                    </td>
                    <td><small class="text-muted">{{ Str::limit($d->dropoff_address, 30) }}</small></td>
                    <td class="fw-semibold">{{ number_format($d->fee) }} ៛</td>
                    <td><span class="badge bg-{{ $sc[$d->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$d->status)) }}</span></td>
                    <td><small class="text-muted">{{ $d->created_at->format('d M H:i') }}</small></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
