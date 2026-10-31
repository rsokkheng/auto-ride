@extends('admin.layout')
@section('title', 'Rides')
@section('page-title', 'Rides')

@php
// Returns stored distance if available, falls back to haversine across all points
function rideDistanceKm($r): ?float {
    if ($r->distance_km) return (float) $r->distance_km;
    if (!$r->pickup_lat || !$r->dropoff_lat) return null;
    $stops = $r->stops ?? collect();
    $points = collect([['lat'=>$r->pickup_lat,'lng'=>$r->pickup_lng]])
        ->concat($stops->map(fn($s)=>['lat'=>$s->lat,'lng'=>$s->lng]))
        ->push(['lat'=>$r->dropoff_lat,'lng'=>$r->dropoff_lng]);
    $total = 0;
    for ($i = 0; $i < $points->count() - 1; $i++) {
        $a = $points[$i]; $b = $points[$i+1];
        $dLat = deg2rad($b['lat']-$a['lat']);
        $dLng = deg2rad($b['lng']-$a['lng']);
        $h = sin($dLat/2)**2 + cos(deg2rad($a['lat']))*cos(deg2rad($b['lat']))*sin($dLng/2)**2;
        $total += 6371 * 2 * atan2(sqrt($h), sqrt(1-$h));
    }
    return round($total * 1.35, 1);
}
@endphp

@section('content')

<x-admin.page-toolbar :subtitle="'Every ride booked in the app. Drivers keep ' . (100 - $commissionPct) . '% of the fare; ' . $commissionPct . '% is platform commission.'">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Create Ride</button>
</x-admin.page-toolbar>

@php
    $tabs = [
        'all'       => ['All Rides', 'fa-route',          'primary'],
        'pending'   => ['In Progress', 'fa-spinner',      'info'],
        'completed' => ['Completed', 'fa-flag-checkered', 'success'],
        'cancelled' => ['Cancelled', 'fa-ban',            'danger'],
    ];
    $statusTone = ['requested' => 'muted', 'pending' => 'warning', 'accepted' => 'info', 'driver_arrived' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
@endphp
<x-admin.stat-grid :cols="4">
    @foreach($tabs as $key => [$label, $icon, $tone])
        <x-admin.stat-card :label="$label" :value="number_format($counts[$key])" :icon="$icon" :tone="$tone"
                           :href="route('admin.rides', array_filter(['tab' => $key, 'search' => $search]))" :active="($tab ?? 'all') === $key" />
    @endforeach
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.rides')" placeholder="Ride #, passenger or driver name / phone" :count="$rides->total()" noun="ride">
            <x-admin.filter-select name="tab" :options="collect($tabs)->except('all')->map(fn ($t) => $t[0])->all()" all="All statuses" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Ride</th>
                    <th>Passenger → Driver</th>
                    <th>Route</th>
                    <th>Status</th>
                    <th class="text-right">Fare</th>
                    <th class="text-right">Driver Net</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rides as $r)
                @php
                    $distKm    = rideDistanceKm($r);
                    $netDriver = $r->fare ? round($r->fare * (1 - $commissionPct / 100)) : null;

                    // Duration: actual if completed, estimated from distance otherwise
                    $durationMin = null;
                    $durationActual = false;
                    if ($r->started_at && $r->completed_at) {
                        $durationMin    = (int) \Carbon\Carbon::parse($r->started_at)->diffInMinutes(\Carbon\Carbon::parse($r->completed_at));
                        $durationActual = true;
                    } elseif ($distKm !== null) {
                        $durationMin = (int) ceil(($distKm / 30) * 60); // 30 km/h avg city speed
                    }
                    $durationLabel = null;
                    if ($durationMin === 0 && $durationActual) {
                        $durationLabel = '< 1 min';
                    } elseif ($durationMin) {
                        $hrs = intdiv($durationMin, 60); $mins = $durationMin % 60;
                        $durationLabel = $hrs ? $hrs . ' h' . ($mins ? ' ' . $mins . ' min' : '') : $mins . ' min';
                    }
                    $stops     = $r->stops ?? collect();
                    $routeData = [
                        'rideId'  => $r->id,
                        'pickup'  => ['address' => $r->pickup_address, 'lat' => $r->pickup_lat, 'lng' => $r->pickup_lng],
                        'stops'   => $stops->map(fn ($s) => ['address' => $s->address, 'lat' => $s->lat, 'lng' => $s->lng, 'arrived_at' => $s->arrived_at?->format('d M H:i')])->values()->all(),
                        'dropoff' => ['address' => $r->dropoff_address, 'lat' => $r->dropoff_lat, 'lng' => $r->dropoff_lng],
                    ];
                @endphp
                <tr data-ride="{{ json_encode([
                        'id' => $r->id, 'passenger_id' => $r->passenger_id, 'driver_id' => $r->driver_id,
                        'pickup_address' => $r->pickup_address, 'dropoff_address' => $r->dropoff_address ?? '',
                        'status' => $r->status, 'fare' => $r->fare ?? '', 'service_type' => $r->service_type ?? '', 'notes' => $r->notes ?? '',
                    ]) }}">
                    <td>
                        <a href="{{ route('admin.rides.show', $r->id) }}" style="font-weight:600;">#{{ $r->id }}</a>
                        <div class="text-muted tw:text-xs">{{ $r->created_at->format('d M Y, H:i') }}</div>
                    </td>
                    <td>
                        <div>{{ $r->passenger?->name ?? '—' }}@if($r->passenger_name) <span class="text-muted tw:text-xs">for {{ $r->passenger_name }}</span>@endif</div>
                        <div class="text-muted tw:text-xs"><i class="fas fa-arrow-right mr-1" style="font-size:.6rem;"></i>{{ $r->driver?->name ?? 'No driver yet' }}</div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-route="{{ json_encode($routeData) }}"
                                onclick="showStopsModal(JSON.parse(this.dataset.route))">
                            <i class="fas fa-map-location-dot mr-1"></i>{{ $distKm !== null ? $distKm . ' km' : 'Route' }}
                        </button>
                        <div class="text-muted tw:text-xs tw:mt-1">
                            @if($durationLabel)<i class="fas fa-clock mr-1"></i>{{ $durationLabel }}{{ $durationActual ? '' : ' est.' }}@endif
                            @if($stops->count()) · {{ $stops->count() }} {{ Str::plural('stop', $stops->count()) }}@endif
                        </div>
                    </td>
                    <td><span class="status-pill tone-{{ $statusTone[$r->status] ?? 'muted' }}">{{ ucfirst(str_replace('_', ' ', $r->status)) }}</span></td>
                    <td class="text-right">
                        @if($r->fare)
                            <div style="font-weight:600;color:var(--ink-900);">{{ number_format($r->fare) }} ៛</div>
                            @if($r->discount_amount > 0)<div class="tw:text-xs" style="color:var(--success);">−{{ number_format($r->discount_amount) }} promo</div>@endif
                        @else
                            <span class="text-muted">Metered</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($netDriver !== null)
                            <div style="font-weight:600;color:var(--success);">{{ number_format($netDriver) }} ៛</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(JSON.parse(this.closest('tr').dataset.ride))"
                                             :delete="route('admin.rides.destroy', $r)" :confirm="'Delete ride #' . $r->id . '?'">
                            <a href="{{ route('admin.rides.show', $r->id) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="View details" data-toggle="tooltip"><i class="fas fa-eye"></i></a>
                        </x-admin.row-actions>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="7" icon="fa-route" title="No rides yet" :clear="route('admin.rides')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$rides" />
</div>

{{-- ─── Stop Route Popup ────────────────────────────────────── --}}
<div class="modal fade" id="stopsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title mb-0"><i class="fas fa-route mr-2"></i>Route — Ride <span id="stopModalRideId"></span></h5>
                <button type="button" class="close text-white" onclick="hideModal(this.closest('.modal').id)"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <ul class="list-unstyled mb-0" id="stopsList"></ul>
            </div>
            <div class="modal-footer py-2">
                <a id="gmapsLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-map-marked-alt mr-1"></i>Open in Google Maps
                </a>
                <button type="button" class="btn btn-sm btn-secondary" onclick="hideModal(this.closest('.modal').id)">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ─── Create / Edit Modal ─────────────────────────────────── --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create Ride</h5>
                <button type="button" class="close" onclick="hideModal(this.closest('.modal').id)"><span>&times;</span></button>
            </div>
            <form id="rideForm" method="POST" action="{{ route('admin.rides.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Passenger <span class="text-danger">*</span></label>
                            <select name="passenger_id" id="f-passenger" class="form-control" required>
                                <option value="">— Select passenger —</option>
                                @foreach($passengers as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Driver</label>
                            <select name="driver_id" id="f-driver" class="form-control">
                                <option value="">— Unassigned —</option>
                                @foreach($drivers as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Pickup Address <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_address" id="f-pickup" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Dropoff Address</label>
                        <input type="text" name="dropoff_address" id="f-dropoff" class="form-control" placeholder="Leave blank for metered ride">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="f-status" class="form-control" required>
                                <option value="requested">Requested</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Fare (KHR ៛)</label>
                            <input type="number" name="fare" id="f-fare" class="form-control" min="0" step="100">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Service Type</label>
                            <input type="text" name="service_type" id="f-service" class="form-control" placeholder="standard, premium…">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="f-notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal(this.closest('.modal').id)">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const storeUrl  = '{{ route('admin.rides.store') }}';
const updateBase = '/admin/rides/';

// ── Modal show/hide (plain DOM — no jQuery/Bootstrap JS plugin dependency,
// so these still work even if that CDN bundle fails to load) ──────────────
function showModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    el.style.display = 'block';
    el.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    if (!document.getElementById(id + '-backdrop')) {
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = id + '-backdrop';
        backdrop.onclick = function () { hideModal(id); };
        document.body.appendChild(backdrop);
    }
}

function hideModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.style.display = 'none';
    document.body.classList.remove('modal-open');
    var backdrop = document.getElementById(id + '-backdrop');
    if (backdrop) backdrop.remove();
}

/* ── Stop route popup ── */
function showStopsModal(route) {
    document.getElementById('stopModalRideId').textContent = '#' + route.rideId;

    var allPoints = [];
    allPoints.push({ type: 'pickup', address: route.pickup.address, lat: route.pickup.lat, lng: route.pickup.lng, arrived_at: null });
    route.stops.forEach(function(s, i) {
        allPoints.push({ type: 'stop', index: i + 1, address: s.address, lat: s.lat, lng: s.lng, arrived_at: s.arrived_at });
    });
    if (route.dropoff && route.dropoff.address) {
        allPoints.push({ type: 'dropoff', address: route.dropoff.address, lat: route.dropoff.lat, lng: route.dropoff.lng, arrived_at: null });
    }

    var html = '';
    allPoints.forEach(function(p, i) {
        var isLast = i === allPoints.length - 1;
        var dotColor, label, labelColor, icon;

        if (p.type === 'pickup') {
            dotColor = '#28a745'; labelColor = '#28a745'; label = 'Pickup'; icon = 'fa-map-marker-alt';
        } else if (p.type === 'stop') {
            dotColor = '#fd7e14'; labelColor = '#fd7e14'; label = 'Stop ' + p.index; icon = 'fa-map-pin';
        } else {
            dotColor = '#dc3545'; labelColor = '#dc3545'; label = 'Final Dropoff'; icon = 'fa-flag-checkered';
        }

        html += '<li style="display:flex;align-items:stretch;">';
        // Line + dot
        html += '<div style="display:flex;flex-direction:column;align-items:center;width:48px;flex-shrink:0;padding:14px 0;">';
        html += '<div style="width:32px;height:32px;border-radius:50%;background:' + dotColor + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
        html += '<i class="fas ' + icon + ' text-white" style="font-size:.8rem;"></i></div>';
        if (!isLast) html += '<div style="width:2px;background:#dee2e6;flex:1;margin-top:3px;"></div>';
        html += '</div>';
        // Content
        html += '<div style="padding:12px 16px 12px 8px;flex:1;' + (!isLast ? 'border-bottom:1px solid #f1f5f9;' : '') + '">';
        html += '<div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.8px;color:' + labelColor + ';font-weight:700;">' + label;
        if (p.arrived_at) html += ' <span style="background:#d1fae5;color:#065f46;padding:2px 6px;border-radius:10px;font-size:.65rem;">✓ ' + p.arrived_at + '</span>';
        html += '</div>';
        html += '<div style="font-size:.9rem;font-weight:600;color:#1a202c;margin-top:3px;">' + (p.address || '<em class="text-muted">TBD</em>') + '</div>';
        if (p.lat) html += '<div style="font-size:.72rem;color:#94a3b8;margin-top:2px;">' + p.lat + ', ' + p.lng + '</div>';
        html += '</div></li>';
    });

    document.getElementById('stopsList').innerHTML = html;

    // Build Google Maps multi-stop link
    var coords = allPoints.filter(function(p) { return p.lat; }).map(function(p) { return p.lat + ',' + p.lng; });
    if (coords.length >= 2) {
        var gmUrl = 'https://www.google.com/maps/dir/' + coords.join('/');
        document.getElementById('gmapsLink').href = gmUrl;
        document.getElementById('gmapsLink').style.display = '';
    } else {
        document.getElementById('gmapsLink').style.display = 'none';
    }

    showModal('stopsModal');
}

/* ── Create / Edit ── */
function openCreate() {
    document.getElementById('modalTitle').textContent = 'Create Ride';
    document.getElementById('rideForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('rideForm').reset();
    showModal('formModal');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Ride #' + d.id;
    document.getElementById('rideForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-passenger').value = d.passenger_id;
    document.getElementById('f-driver').value = d.driver_id || '';
    document.getElementById('f-pickup').value = d.pickup_address;
    document.getElementById('f-dropoff').value = d.dropoff_address;
    document.getElementById('f-status').value = d.status;
    document.getElementById('f-fare').value = d.fare;
    document.getElementById('f-service').value = d.service_type;
    document.getElementById('f-notes').value = d.notes;
    showModal('formModal');
}
</script>
@endpush
