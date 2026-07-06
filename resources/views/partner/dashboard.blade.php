@extends('partner.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<style>
.stat-card { border-radius: 12px; padding: 20px 24px; color: #fff; position: relative; overflow: hidden; }
.stat-card .stat-icon { position: absolute; right: 16px; top: 16px; font-size: 2.5rem; opacity: .15; }
.stat-card .stat-value { font-size: 2rem; font-weight: 800; line-height: 1; }
.stat-card .stat-label { font-size: .8rem; opacity: .85; margin-top: 4px; }
.status-badge { font-size: .7rem; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
</style>
@endpush

@section('content')

{{-- Stats Row --}}
<div class="row">
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <i class="fas fa-box stat-icon"></i>
            <div class="stat-value">{{ $stats['today'] }}</div>
            <div class="stat-label">Orders Today</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#f59e0b,#d97706)">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value">{{ $stats['created'] + $stats['assigned'] }}</div>
            <div class="stat-label">Pending / Assigned</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#8b5cf6,#6d28d9)">
            <i class="fas fa-truck stat-icon"></i>
            <div class="stat-value">{{ $stats['in_transit'] }}</div>
            <div class="stat-label">In Transit</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#10b981,#059669)">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value">{{ $stats['delivered'] }}</div>
            <div class="stat-label">Delivered</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-center p-3">
            <div class="text-muted small">This Week</div>
            <div class="font-weight-bold h4 mb-0">{{ $stats['week'] }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center p-3">
            <div class="text-muted small">This Month</div>
            <div class="font-weight-bold h4 mb-0">{{ $stats['month'] }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Cancelled</div>
            <div class="font-weight-bold h4 mb-0 text-danger">{{ $stats['cancelled'] }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Success Rate</div>
            <div class="font-weight-bold h4 mb-0 text-success">{{ $successRate }}%</div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Active Deliveries --}}
    <div class="col-md-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-truck text-primary mr-2"></i>Live Active Deliveries</h5>
                <a href="{{ route('partner.orders', ['status' => 'in_transit']) }}" class="btn btn-xs btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th><th>Recipient</th><th>Status</th><th>Driver</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($active as $d)
                        <tr>
                            <td class="text-muted small">{{ $d->id }}</td>
                            <td>
                                <div class="font-weight-bold">{{ $d->recipient_name }}</div>
                                <small class="text-muted">{{ $d->recipient_phone }}</small>
                            </td>
                            <td>
                                @php $sc = ['assigned'=>'warning','accepted'=>'info','picked_up'=>'primary','in_transit'=>'purple']; @endphp
                                <span class="badge badge-{{ $sc[$d->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $d->status)) }}
                                </span>
                            </td>
                            <td>
                                @if($d->driver)
                                    <div>{{ $d->driver->name }}</div>
                                    <small class="text-muted">{{ $d->driver->phone }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('partner.orders.show', $d) }}" class="btn btn-xs btn-outline-secondary">
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

    {{-- Quick Actions + Wallet --}}
    <div class="col-md-4 mb-3">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-wallet text-success mr-2"></i>Wallet</h5></div>
            <div class="card-body text-center">
                <div class="h3 font-weight-bold text-success mb-0">{{ number_format($partner->wallet_balance) }} ៛</div>
                <small class="text-muted">≈ ${{ number_format($partner->wallet_balance / 4000, 2) }}</small>
                <div class="mt-3">
                    <a href="{{ route('partner.wallet') }}" class="btn btn-sm btn-outline-success btn-block">
                        <i class="fas fa-history mr-1"></i>View Wallet
                    </a>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-bolt text-warning mr-2"></i>Quick Actions</h5></div>
            <div class="card-body p-2">
                <a href="{{ route('partner.orders.create') }}" class="btn btn-danger btn-block mb-2">
                    <i class="fas fa-plus mr-1"></i>New Delivery Order
                </a>
                <a href="{{ route('partner.orders') }}" class="btn btn-outline-primary btn-block mb-2">
                    <i class="fas fa-list mr-1"></i>All Orders
                </a>
                <a href="{{ route('partner.reports') }}" class="btn btn-outline-secondary btn-block">
                    <i class="fas fa-chart-bar mr-1"></i>View Reports
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Recent Orders --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-history mr-2"></i>Recent Orders</h5>
        <a href="{{ route('partner.orders') }}" class="btn btn-xs btn-outline-secondary">View All</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr><th>#</th><th>Ref</th><th>Recipient</th><th>Dropoff</th><th>Fee</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse($recent as $d)
                @php
                    $sc = ['created'=>'light','assigned'=>'warning','accepted'=>'info','picked_up'=>'primary','in_transit'=>'secondary','delivered'=>'success','completed'=>'success','cancelled'=>'danger'];
                @endphp
                <tr>
                    <td><a href="{{ route('partner.orders.show', $d) }}" class="font-weight-bold">#{{ $d->id }}</a></td>
                    <td><small class="text-muted">{{ $d->partner_reference ?? '—' }}</small></td>
                    <td>
                        <div>{{ $d->recipient_name }}</div>
                        <small class="text-muted">{{ $d->recipient_phone }}</small>
                    </td>
                    <td><small class="text-muted">{{ Str::limit($d->dropoff_address, 30) }}</small></td>
                    <td>{{ number_format($d->fee) }} ៛</td>
                    <td><span class="badge badge-{{ $sc[$d->status] ?? 'secondary' }} text-{{ in_array($d->status,['created']) ? 'dark' : '' }}">{{ ucfirst(str_replace('_',' ',$d->status)) }}</span></td>
                    <td><small>{{ $d->created_at->format('d M H:i') }}</small></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
