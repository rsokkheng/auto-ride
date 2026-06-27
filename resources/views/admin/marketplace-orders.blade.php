@extends('admin.layout')
@section('title', 'Marketplace Orders')
@section('page-title', 'Marketplace Orders')

@section('content')

{{-- Flash messages --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.marketplace-orders') }}" class="form-inline flex-wrap gap-2">
            <select name="order_type" class="form-control form-control-sm mr-2 mb-1">
                <option value="">All Types</option>
                <option value="purchase" {{ request('order_type') == 'purchase' ? 'selected' : '' }}>Sale (Purchase)</option>
                <option value="rent"     {{ request('order_type') == 'rent'     ? 'selected' : '' }}>Rent</option>
            </select>
            <select name="status" class="form-control form-control-sm mr-2 mb-1">
                <option value="">All Statuses</option>
                <option value="pending"   {{ request('status') == 'pending'   ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            <button class="btn btn-sm btn-primary mr-1 mb-1"><i class="fas fa-filter mr-1"></i>Filter</button>
            <a href="{{ route('admin.marketplace-orders') }}" class="btn btn-sm btn-secondary mb-1">Reset</a>
        </form>
    </div>
</div>

{{-- Summary badges --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pending</span>
                <span class="info-box-number">{{ $orders->getCollection()->where('status','pending')->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Confirmed</span>
                <span class="info-box-number">{{ $orders->getCollection()->where('status','confirmed')->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-check-double"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Completed</span>
                <span class="info-box-number">{{ $orders->getCollection()->where('status','completed')->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-danger"><i class="fas fa-times"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Cancelled</span>
                <span class="info-box-number">{{ $orders->getCollection()->where('status','cancelled')->count() }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Orders table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-shopping-bag mr-1"></i> Marketplace Orders</h3>
        <span class="badge badge-secondary">{{ $orders->total() }} total</span>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead class="thead-light">
                <tr>
                    <th>#ID</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Buyer</th>
                    <th>Seller</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>Rent Dates</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    {{-- ID --}}
                    <td class="align-middle font-weight-bold">#{{ $order->id }}</td>

                    {{-- Product --}}
                    <td class="align-middle" style="max-width:180px; white-space:normal;">
                        @if($order->product)
                            <div class="d-flex align-items-center">
                                @if($order->product->images->first())
                                    <img src="{{ $order->product->images->first()->full_url }}"
                                         class="img-thumbnail mr-2" style="width:40px;height:40px;object-fit:cover;" alt="">
                                @else
                                    <div class="bg-light border rounded mr-2 d-flex align-items-center justify-content-center"
                                         style="width:40px;height:40px;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                @endif
                                <span class="text-wrap" style="max-width:130px;">{{ $order->product->title }}</span>
                            </div>
                        @else
                            <span class="text-muted">(deleted)</span>
                        @endif
                    </td>

                    {{-- Order Type --}}
                    <td class="align-middle">
                        @if($order->order_type === 'rent')
                            <span class="badge badge-info">Rent</span>
                        @else
                            <span class="badge badge-primary">Sale</span>
                        @endif
                    </td>

                    {{-- Buyer --}}
                    <td class="align-middle" style="max-width:140px; white-space:normal;">
                        @if($order->isGuest())
                            <div>
                                <span class="badge badge-secondary badge-sm">Guest</span><br>
                                <small>{{ $order->guest_name ?? '—' }}</small><br>
                                <small class="text-muted">{{ $order->guest_phone ?? '' }}</small>
                            </div>
                        @elseif($order->buyer)
                            <div>
                                <div>{{ $order->buyer->name }}</div>
                                <small class="text-muted">{{ $order->buyer->phone ?? '' }}</small>
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Seller --}}
                    <td class="align-middle" style="max-width:140px; white-space:normal;">
                        @if($order->seller)
                            <div>
                                <div>{{ $order->seller->name }}</div>
                                <small class="text-muted">{{ $order->seller->phone ?? '' }}</small>
                            </div>
                        @elseif($order->product && $order->product->seller)
                            <div>
                                <div>{{ $order->product->seller->name }}</div>
                                <small class="text-muted">{{ $order->product->seller->phone ?? '' }}</small>
                            </div>
                        @elseif($order->product && $order->product->entry_type === 'guest')
                            <div>
                                <span class="badge badge-secondary badge-sm">Guest Seller</span><br>
                                <small>{{ $order->product->guest_name ?? '—' }}</small>
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Qty --}}
                    <td class="align-middle text-center">{{ $order->quantity }}</td>

                    {{-- Unit Price --}}
                    <td class="align-middle">${{ number_format($order->unit_price, 2) }}</td>

                    {{-- Total --}}
                    <td class="align-middle font-weight-bold">${{ number_format($order->total_price, 2) }}</td>

                    {{-- Rent Dates --}}
                    <td class="align-middle" style="min-width:120px;">
                        @if($order->order_type === 'rent' && $order->rent_start_date)
                            <small>
                                {{ $order->rent_start_date->format('d M Y') }}<br>
                                → {{ $order->rent_end_date?->format('d M Y') ?? '?' }}
                            </small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Payment --}}
                    <td class="align-middle">
                        <div>
                            @php
                                $pmLabel = ['cash'=>'Cash','wallet'=>'Wallet','aba'=>'ABA','wing'=>'Wing','other_online'=>'Online'][$order->payment_method] ?? ucfirst($order->payment_method ?? '—');
                                $psClass = match($order->payment_status) {
                                    'paid'    => 'badge-success',
                                    'unpaid'  => 'badge-warning',
                                    'refunded'=> 'badge-info',
                                    default   => 'badge-secondary',
                                };
                            @endphp
                            <span class="badge {{ $psClass }}">{{ ucfirst($order->payment_status ?? '—') }}</span><br>
                            <small class="text-muted">{{ $pmLabel }}</small>
                        </div>
                    </td>

                    {{-- Status --}}
                    <td class="align-middle">
                        @php
                            $statusClass = match($order->status) {
                                'pending'   => 'badge-warning',
                                'confirmed' => 'badge-info',
                                'completed' => 'badge-success',
                                'cancelled' => 'badge-danger',
                                default     => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                    </td>

                    {{-- Date --}}
                    <td class="align-middle">
                        <small>{{ $order->created_at->format('d M Y') }}<br>{{ $order->created_at->format('H:i') }}</small>
                    </td>

                    {{-- Actions --}}
                    <td class="align-middle" style="min-width:150px;">
                        <div class="btn-group-vertical btn-group-sm">
                            @if($order->status === 'pending')
                                <form method="POST" action="{{ route('admin.marketplace-orders.confirm', $order) }}" class="mb-1">
                                    @csrf
                                    <button class="btn btn-sm btn-info btn-block"
                                            onclick="return confirm('Confirm order #{{ $order->id }}?')">
                                        <i class="fas fa-check mr-1"></i> Confirm
                                    </button>
                                </form>
                            @endif
                            @if($order->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.marketplace-orders.complete', $order) }}" class="mb-1">
                                    @csrf
                                    <button class="btn btn-sm btn-success btn-block"
                                            onclick="return confirm('Mark order #{{ $order->id }} as completed?')">
                                        <i class="fas fa-check-double mr-1"></i> Complete
                                    </button>
                                </form>
                            @endif
                            @if(! in_array($order->status, ['completed', 'cancelled']))
                                <form method="POST" action="{{ route('admin.marketplace-orders.cancel', $order) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-danger btn-block"
                                            onclick="return confirm('Cancel order #{{ $order->id }}? This cannot be undone.')">
                                        <i class="fas fa-times mr-1"></i> Cancel
                                    </button>
                                </form>
                            @endif
                            @if(in_array($order->status, ['completed', 'cancelled']))
                                <span class="text-muted small">No actions</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        No orders found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer clearfix">
        {{ $orders->links() }}
    </div>
    @endif
</div>

@endsection
