@extends('admin.layout')
@section('title', 'Car Rentals')
@section('page-title', 'Car Rentals')

@section('content')

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
        <form method="GET" action="{{ route('admin.car-rentals') }}" class="form-inline flex-wrap">
            <select name="status" class="form-control form-control-sm mr-2 mb-1">
                <option value="">All Statuses</option>
                <option value="pending"   {{ request('status') == 'pending'   ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="active"    {{ request('status') == 'active'    ? 'selected' : '' }}>Active</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            <select name="vehicle_type" class="form-control form-control-sm mr-2 mb-1">
                <option value="">All Vehicles</option>
                @foreach(['motorcycle','tuk_tuk','electric','sedan','suv','van','truck'] as $vt)
                    <option value="{{ $vt }}" {{ request('vehicle_type') == $vt ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$vt)) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary mr-1 mb-1"><i class="fas fa-filter mr-1"></i>Filter</button>
            <a href="{{ route('admin.car-rentals') }}" class="btn btn-sm btn-secondary mb-1">Reset</a>
        </form>
    </div>
</div>

{{-- Summary --}}
<div class="row mb-3">
    @php
        $all = $rentals->getCollection();
    @endphp
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pending</span>
                <span class="info-box-number">{{ $all->where('status','pending')->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Confirmed</span>
                <span class="info-box-number">{{ $all->where('status','confirmed')->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-check-double"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Completed</span>
                <span class="info-box-number">{{ $all->where('status','completed')->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-danger"><i class="fas fa-times"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Cancelled</span>
                <span class="info-box-number">{{ $all->where('status','cancelled')->count() }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-car mr-1"></i> Car Rental Bookings</h3>
        <span class="badge badge-secondary">{{ $rentals->total() }} total</span>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead class="thead-light">
                <tr>
                    <th>#ID</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Vehicle</th>
                    <th>Pickup Location</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Total (KHR)</th>
                    <th>Total (USD)</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Booked At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rentals as $rental)
                @php
                    $dailyUsd = match($rental->vehicle_type) {
                        'motorcycle' => 5,
                        'tuk_tuk'   => 7,
                        'electric'  => 9,
                        'sedan'     => 10,
                        'suv'       => 15,
                        'van'       => 18,
                        'truck'     => 25,
                        default     => 10,
                    };
                    $totalUsd = $dailyUsd * $rental->total_days;
                @endphp
                <tr>
                    {{-- ID --}}
                    <td class="align-middle font-weight-bold">#{{ $rental->id }}</td>

                    {{-- Customer --}}
                    <td class="align-middle" style="max-width:140px;white-space:normal;">
                        @if($rental->user)
                            <div>{{ $rental->user->name }}</div>
                            <small class="text-muted">{{ $rental->user->phone ?? '' }}</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Marketplace Product --}}
                    <td class="align-middle" style="max-width:160px;white-space:normal;">
                        @if($rental->marketplaceProduct)
                            <div class="d-flex align-items-center">
                                @if($rental->marketplaceProduct->images->first())
                                    <img src="{{ $rental->marketplaceProduct->images->first()->full_url }}"
                                         class="img-thumbnail mr-2" style="width:36px;height:36px;object-fit:cover;" alt="">
                                @endif
                                <small>{{ $rental->marketplaceProduct->title }}</small>
                            </div>
                        @else
                            <span class="text-muted small">Generic</span>
                        @endif
                    </td>

                    {{-- Vehicle Type --}}
                    <td class="align-middle">
                        <span class="badge badge-secondary">
                            {{ ucfirst(str_replace('_',' ', $rental->vehicle_type)) }}
                        </span>
                    </td>

                    {{-- Pickup --}}
                    <td class="align-middle" style="max-width:180px;white-space:normal;">
                        <small>{{ $rental->pickup_location }}</small>
                    </td>

                    {{-- Dates --}}
                    <td class="align-middle">
                        <small>
                            {{ $rental->start_date->format('d M Y') }}<br>
                            → {{ $rental->end_date->format('d M Y') }}
                        </small>
                    </td>

                    {{-- Days --}}
                    <td class="align-middle text-center">{{ $rental->total_days }}</td>

                    {{-- Total KHR --}}
                    <td class="align-middle">{{ number_format($rental->total_amount_khr) }}</td>

                    {{-- Total USD --}}
                    <td class="align-middle font-weight-bold">${{ number_format($totalUsd, 2) }}</td>

                    {{-- Payment --}}
                    <td class="align-middle">
                        <span class="text-capitalize">{{ str_replace('_',' ', $rental->payment_method) }}</span>
                    </td>

                    {{-- Status --}}
                    <td class="align-middle">
                        @php
                            $cls = match($rental->status) {
                                'pending'   => 'badge-warning',
                                'confirmed' => 'badge-info',
                                'active'    => 'badge-primary',
                                'completed' => 'badge-success',
                                'cancelled' => 'badge-danger',
                                default     => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $cls }}">{{ ucfirst($rental->status) }}</span>
                    </td>

                    {{-- Booked At --}}
                    <td class="align-middle">
                        <small>{{ $rental->created_at->format('d M Y') }}<br>{{ $rental->created_at->format('H:i') }}</small>
                    </td>

                    {{-- Actions --}}
                    <td class="align-middle" style="min-width:140px;">
                        <div class="btn-group-vertical btn-group-sm">
                            @if($rental->status === 'pending')
                                <form method="POST" action="{{ route('admin.car-rentals.confirm', $rental) }}" class="mb-1">
                                    @csrf
                                    <button class="btn btn-sm btn-info btn-block"
                                            onclick="return confirm('Confirm rental #{{ $rental->id }}?')">
                                        <i class="fas fa-check mr-1"></i> Confirm
                                    </button>
                                </form>
                            @endif
                            @if($rental->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.car-rentals.complete', $rental) }}" class="mb-1">
                                    @csrf
                                    <button class="btn btn-sm btn-success btn-block"
                                            onclick="return confirm('Mark rental #{{ $rental->id }} as completed?')">
                                        <i class="fas fa-check-double mr-1"></i> Complete
                                    </button>
                                </form>
                            @endif
                            @if(! in_array($rental->status, ['completed', 'cancelled']))
                                <form method="POST" action="{{ route('admin.car-rentals.cancel', $rental) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-danger btn-block"
                                            onclick="return confirm('Cancel rental #{{ $rental->id }}?')">
                                        <i class="fas fa-times mr-1"></i> Cancel
                                    </button>
                                </form>
                            @endif
                            @if(in_array($rental->status, ['completed','cancelled']))
                                <span class="text-muted small">No actions</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center text-muted py-4">
                        <i class="fas fa-car fa-2x mb-2 d-block"></i>
                        No rentals found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($rentals->hasPages())
    <div class="card-footer clearfix">
        {{ $rentals->links() }}
    </div>
    @endif
</div>

@endsection
