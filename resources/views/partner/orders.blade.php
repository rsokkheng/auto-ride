@extends('partner.layout')
@section('title', 'Orders')
@section('page-title', 'Order Management')

@section('content')

{{-- ── Filter Bar ──────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-5">
    <form method="GET" action="{{ route('partner.orders') }}" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Search</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                    <i class="fas fa-search text-xs"></i>
                </span>
                <input type="text" name="search"
                       class="form-control form-control-sm ps-8"
                       placeholder="Name / phone / ref / QR…"
                       value="{{ $search ?? '' }}">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                @foreach(['created','assigned','accepted','picked_up','in_transit','delivered','completed','cancelled'] as $s)
                    <option value="{{ $s }}" {{ ($status ?? '') == $s ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_',' ',$s)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $from ?? '' }}">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $to ?? '' }}">
        </div>
        <div class="flex gap-2 items-end">
            <button class="btn btn-sm btn-primary">
                <i class="fas fa-search me-1"></i>Search
            </button>
            <a href="{{ route('partner.orders') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
        <div class="ms-auto">
            <a href="{{ route('partner.orders.create') }}"
               class="btn btn-sm text-white"
               style="background:linear-gradient(135deg,#e63946,#c1121f);border:none">
                <i class="fas fa-plus me-1"></i>New Order
            </a>
        </div>
    </form>
</div>

{{-- ── Orders Table ─────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="fas fa-box text-slate-400"></i> Orders
        </h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
            {{ $orders->total() }} total
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>Ref</th><th>Recipient</th><th>Dropoff</th>
                    <th>Pkg Value</th><th>Fee</th><th>Driver</th><th>Status</th><th>Date</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $d)
                @php
                $sc = ['created'=>'secondary','assigned'=>'warning','accepted'=>'info','picked_up'=>'primary',
                       'in_transit'=>'dark','delivered'=>'success','completed'=>'success','cancelled'=>'danger'];
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('partner.orders.show', $d) }}" class="fw-bold text-decoration-none text-primary">
                            #{{ $d->id }}
                        </a>
                    </td>
                    <td><small class="text-muted">{{ $d->partner_reference ?? '—' }}</small></td>
                    <td>
                        <div class="fw-semibold">{{ $d->recipient_name }}</div>
                        <small class="text-muted">{{ $d->recipient_phone }}</small>
                    </td>
                    <td><small class="text-muted">{{ Str::limit($d->dropoff_address, 28) }}</small></td>
                    <td>
                        @if(($d->package_amount ?? 0) > 0)
                            <span class="fw-semibold">{{ number_format($d->package_amount) }} ៛</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td><strong>{{ number_format($d->fee) }} ៛</strong></td>
                    <td>
                        @if($d->driver)
                            <div class="small fw-semibold">{{ $d->driver->name }}</div>
                            <small class="text-muted">{{ $d->driver->phone }}</small>
                        @else
                            <span class="text-muted small">Unassigned</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-{{ $sc[$d->status] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_',' ',$d->status)) }}
                        </span>
                    </td>
                    <td><small class="text-muted">{{ $d->created_at->format('d M H:i') }}</small></td>
                    <td>
                        <a href="{{ route('partner.orders.show', $d) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-12">
                        <i class="fas fa-box-open fa-2x text-slate-300 mb-3 d-block"></i>
                        <p class="text-slate-500 mb-0">No orders found.</p>
                        <a href="{{ route('partner.orders.create') }}" class="btn btn-sm btn-primary mt-3">
                            Create your first order
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <p class="text-sm text-slate-500 mb-0">
            Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }}
            of {{ $orders->total() }} orders
        </p>
        @if($orders->hasPages())
            {{ $orders->links('pagination::bootstrap-5') }}
        @endif
    </div>
</div>

@endsection
