@extends('partner.layout')
@section('title', 'Orders')
@section('page-title', 'Order Management')

@section('content')

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('partner.orders') }}" class="form-inline flex-wrap" style="gap:6px">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Recipient name / phone / ref / QR…" value="{{ $search ?? '' }}" style="min-width:200px">
            <select name="status" class="form-control form-control-sm">
                <option value="">All Statuses</option>
                @foreach(['created','assigned','accepted','picked_up','in_transit','delivered','completed','cancelled'] as $s)
                    <option value="{{ $s }}" {{ ($status ?? '') == $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $from ?? '' }}">
            <input type="date" name="date_to"   class="form-control form-control-sm" value="{{ $to ?? '' }}">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i>Search</button>
            <a href="{{ route('partner.orders') }}" class="btn btn-sm btn-secondary">Reset</a>
            <a href="{{ route('partner.orders.create') }}" class="btn btn-sm btn-danger ml-auto">
                <i class="fas fa-plus mr-1"></i>New Order
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-box mr-2"></i>Orders</h5>
        <span class="badge badge-secondary">{{ $orders->total() }} total</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0 text-nowrap">
            <thead class="thead-light">
                <tr>
                    <th>#</th><th>Ref</th><th>Recipient</th><th>Dropoff</th>
                    <th>Fee</th><th>Driver</th><th>Status</th><th>Date</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $d)
                @php
                    $sc = ['created'=>'light','assigned'=>'warning','accepted'=>'info','picked_up'=>'primary',
                           'in_transit'=>'secondary','delivered'=>'success','completed'=>'success','cancelled'=>'danger'];
                @endphp
                <tr>
                    <td><a href="{{ route('partner.orders.show', $d) }}" class="font-weight-bold text-primary">#{{ $d->id }}</a></td>
                    <td><small class="text-muted">{{ $d->partner_reference ?? '—' }}</small></td>
                    <td>
                        <div>{{ $d->recipient_name }}</div>
                        <small class="text-muted">{{ $d->recipient_phone }}</small>
                    </td>
                    <td><small>{{ Str::limit($d->dropoff_address, 28) }}</small></td>
                    <td><strong>{{ number_format($d->fee) }} ៛</strong></td>
                    <td>
                        @if($d->driver)
                            <div>{{ $d->driver->name }}</div>
                            <small class="text-muted">{{ $d->driver->phone }}</small>
                        @else
                            <span class="text-muted small">Unassigned</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $sc[$d->status] ?? 'secondary' }} {{ $d->status === 'created' ? 'text-dark' : '' }}">
                            {{ ucfirst(str_replace('_',' ',$d->status)) }}
                        </span>
                    </td>
                    <td><small class="text-muted">{{ $d->created_at->format('d M H:i') }}</small></td>
                    <td>
                        <a href="{{ route('partner.orders.show', $d) }}" class="btn btn-xs btn-outline-secondary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="fas fa-box-open fa-2x mb-2 d-block"></i>No orders found.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
