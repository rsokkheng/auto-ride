@extends('admin.layout')
@section('title', 'Car Rentals')
@section('page-title', 'Car Rentals')

@php
    $statusMeta = [
        'pending'   => ['Pending',   'tone-warning', 'fa-clock'],
        'confirmed' => ['Confirmed', 'tone-info',    'fa-circle-check'],
        'active'    => ['Active',    'tone-primary', 'fa-key'],
        'completed' => ['Completed', 'tone-success', 'fa-flag-checkered'],
        'cancelled' => ['Cancelled', 'tone-danger',  'fa-ban'],
    ];
    $current  = request('status');
    $filtered = filled($search) || $current || request('vehicle_type');
    $initials = fn (?string $name) => collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '?';
@endphp

@section('content')

<div class="page-toolbar">
    <p class="page-subtitle">Vehicle rental bookings from the app and the marketplace. Amounts are what the customer was charged.</p>
</div>

{{-- Status overview — click to filter (Tailwind grid, tw: prefix) --}}
@php $total = $counts->sum(); @endphp
<div class="tw:mb-4 tw:grid tw:grid-cols-2 tw:gap-3 tw:md:grid-cols-3 tw:xl:grid-cols-6">
    <a href="{{ route('admin.car-rentals', request()->except(['status', 'page'])) }}" class="stat-card {{ ! $current ? 'is-active' : '' }}">
        <span class="stat-icon tone-muted"><i class="fas fa-layer-group"></i></span>
        <div><div class="stat-label">All</div><div class="stat-value">{{ number_format($total) }}</div></div>
    </a>
    @foreach($statusMeta as $key => [$label, $tone, $icon])
        <a href="{{ route('admin.car-rentals', array_merge(request()->except('page'), ['status' => $key])) }}"
           class="stat-card {{ $current === $key ? 'is-active' : '' }}">
            <span class="stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></span>
            <div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ number_format($counts[$key] ?? 0) }}</div></div>
        </a>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.car-rentals') }}" class="filter-bar">
            <div class="search-input">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Rental #, renter name, phone or email" value="{{ $search ?? '' }}">
            </div>
            <select name="status" class="form-control form-control-sm">
                <option value="">All statuses</option>
                @foreach($statusMeta as $key => [$label])
                    <option value="{{ $key }}" @selected($current === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="vehicle_type" class="form-control form-control-sm">
                <option value="">All vehicles</option>
                @foreach(\App\Models\CarRental::VEHICLE_TYPES as $vt)
                    <option value="{{ $vt }}" @selected(request('vehicle_type') === $vt)>{{ \App\Models\CarRental::VEHICLE_TYPE_LABELS[$vt] }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i> Apply</button>
            @if($filtered)
                <a href="{{ route('admin.car-rentals') }}" class="btn btn-sm btn-secondary">Clear</a>
            @endif
            <span class="ml-auto text-muted" style="font-size:.78rem;">
                {{ number_format($rentals->total()) }} {{ Str::plural('rental', $rentals->total()) }}
            </span>
        </form>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Rental</th>
                    <th>Renter</th>
                    <th>Vehicle</th>
                    <th>Period &amp; pickup</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rentals as $rental)
                @php
                    $product = $rental->marketplaceProduct;
                    $image   = $product?->images->first()?->full_url;
                    [$sLabel, $sTone] = $statusMeta[$rental->status] ?? [ucfirst($rental->status), 'tone-muted'];
                    $renter  = $rental->isGuest() ? $rental->guest_name : $rental->user?->name;
                    $phone   = $rental->isGuest() ? $rental->guest_phone : $rental->user?->phone;
                @endphp
                <tr>
                    <td>
                        <div class="font-weight-bold" style="color:var(--ink-900);">#{{ $rental->id }}</div>
                        <div class="text-muted" style="font-size:.74rem;">{{ $rental->created_at->format('d M, H:i') }}</div>
                    </td>

                    <td>
                        @if($renter || $phone)
                            <div class="person">
                                <span class="avatar-chip">{{ $initials($renter) }}</span>
                                <div style="min-width:0;">
                                    <div class="person-name" style="max-width:130px;">
                                        {{ $renter ?? '—' }}
                                        @if($rental->isGuest())<span class="badge badge-secondary ml-1">Guest</span>@endif
                                    </div>
                                    <div class="person-sub">{{ $phone ?? '' }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td>
                        <div class="d-flex align-items-center" style="gap:.6rem;">
                            @if($image)
                                <img src="{{ $image }}" class="thumb" alt="" loading="lazy"
                                     onerror="this.outerHTML='<span class=&quot;thumb-placeholder&quot;><i class=&quot;fas fa-car-side&quot;></i></span>'">
                            @else
                                <span class="thumb-placeholder"><i class="fas fa-car-side"></i></span>
                            @endif
                            <div style="min-width:0;">
                                <div class="text-truncate" style="max-width:150px;font-weight:500;color:var(--ink-900);"
                                     title="{{ $product?->title }}">
                                    {{ $product?->title ?? 'Standard rental' }}
                                </div>
                                <div class="text-muted" style="font-size:.74rem;">{{ $rental->vehicleLabel() }}</div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div>{{ $rental->start_date->format('d M') }} – {{ $rental->end_date->format('d M Y') }}</div>
                        <div class="text-muted" style="font-size:.74rem;">
                            {{ $rental->total_days }} {{ Str::plural('day', $rental->total_days) }} · ${{ number_format($rental->dailyRateUsd(), 2) }}/day
                        </div>
                        <div class="text-muted text-truncate" style="font-size:.74rem;max-width:165px;" title="{{ $rental->pickup_location }}">
                            <i class="fas fa-location-dot mr-1" style="color:var(--ink-400);"></i>{{ $rental->pickup_location }}
                        </div>
                    </td>

                    <td class="text-right">
                        <div class="font-weight-bold" style="color:var(--ink-900);">{{ number_format($rental->total_amount_khr) }} ៛</div>
                        <div class="text-muted" style="font-size:.74rem;">
                            ${{ number_format($rental->total_amount_khr / \App\Models\CarRental::KHR_PER_USD, 2) }}
                            · <span class="text-capitalize">{{ str_replace('_', ' ', $rental->payment_method ?? '—') }}</span>
                        </div>
                    </td>

                    <td><span class="status-pill {{ $sTone }}">{{ $sLabel }}</span></td>

                    <td class="text-center">
                        <div class="action-group">
                            @if($rental->status === 'pending')
                                <button type="button" class="btn btn-sm btn-outline-info btn-icon" title="Confirm rental" data-toggle="tooltip"
                                        onclick="showConfirm('confirm','#{{ $rental->id }}','{{ route('admin.car-rentals.confirm', $rental) }}')">
                                    <i class="fas fa-check"></i>
                                </button>
                            @endif
                            @if($rental->status === 'confirmed')
                                <button type="button" class="btn btn-sm btn-outline-success btn-icon" title="Mark completed" data-toggle="tooltip"
                                        onclick="showConfirm('complete','#{{ $rental->id }}','{{ route('admin.car-rentals.complete', $rental) }}')">
                                    <i class="fas fa-check-double"></i>
                                </button>
                            @endif
                            @if(! in_array($rental->status, ['completed', 'cancelled']))
                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="Cancel rental" data-toggle="tooltip"
                                        onclick="showConfirm('cancel','#{{ $rental->id }}','{{ route('admin.car-rentals.cancel', $rental) }}')">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-car-side"></i></div>
                            <h6>{{ $filtered ? 'No rentals match these filters' : 'No rentals yet' }}</h6>
                            <p class="mb-0" style="font-size:.82rem;">
                                @if($filtered)
                                    Try another search or <a href="{{ route('admin.car-rentals') }}">clear the filters</a>.
                                @else
                                    Bookings made in the app will appear here.
                                @endif
                            </p>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($rentals->hasPages())
        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between" style="gap:.5rem;">
            <span class="text-muted" style="font-size:.78rem;">
                Showing {{ $rentals->firstItem() }}–{{ $rentals->lastItem() }} of {{ number_format($rentals->total()) }}
            </span>
            {{ $rentals->onEachSide(1)->links() }}
        </div>
    @endif
</div>

{{-- Confirm modal --}}
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center pt-4 pb-3">
                <span id="modalIconWrap" class="stat-icon mx-auto mb-3" style="width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
                    <i id="modalIcon" class="fas fa-question"></i>
                </span>
                <h5 class="mb-1" id="modalTitle" style="font-size:1rem;">Confirm action</h5>
                <p id="modalMessage" class="text-muted mb-0" style="font-size:.84rem;"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Go back</button>
                <form id="actionForm" method="POST" class="m-0">
                    @csrf
                    <button type="submit" id="modalBtn" class="btn btn-primary"><span id="modalBtnText">Confirm</span></button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showConfirm(action, label, url) {
    const config = {
        confirm:  { title: 'Confirm rental ' + label + '?',  msg: 'The renter will be notified that the booking is confirmed.', icon: 'fa-check',        tone: 'tone-info',    btn: 'btn-info',    text: 'Confirm rental' },
        complete: { title: 'Complete rental ' + label + '?', msg: 'Marks the vehicle as returned and the rental as finished.',   icon: 'fa-check-double', tone: 'tone-success', btn: 'btn-success', text: 'Mark completed' },
        cancel:   { title: 'Cancel rental ' + label + '?',   msg: 'The renter will be notified. This cannot be undone.',          icon: 'fa-xmark',        tone: 'tone-danger',  btn: 'btn-danger',  text: 'Cancel rental' },
    };
    const c = config[action];
    document.getElementById('modalTitle').textContent    = c.title;
    document.getElementById('modalMessage').textContent  = c.msg;
    document.getElementById('modalIcon').className       = 'fas ' + c.icon;
    document.getElementById('modalIconWrap').className   = 'stat-icon mx-auto mb-3 ' + c.tone;
    document.getElementById('modalBtn').className        = 'btn ' + c.btn;
    document.getElementById('modalBtnText').textContent  = c.text;
    document.getElementById('actionForm').action         = url;
    $('#actionModal').modal('show');
}
</script>
@endpush

@endsection
