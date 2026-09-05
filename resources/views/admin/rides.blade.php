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

@include('admin.partials.search-box', [
    'route' => route('admin.rides'),
    'search' => $search ?? '',
    'placeholder' => 'Search by ride #, passenger, or driver name/phone…',
])

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Ride Orders</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Ride
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Passenger</th>
                    <th>Driver</th>
                    <th>Pickup</th>
                    <th>Dropoff <small class="text-muted font-weight-normal">(click for stops)</small></th>
                    <th>Status</th>
                    <th class="text-right">Dist (km)</th>
                    <th class="text-center">Est. Time</th>
                    <th class="text-right">Fare ៛</th>
                    <th class="text-right">Net Driver ៛</th>
                    <th>Date &amp; Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rides as $r)
                @php
                    $sc = ['requested'=>'secondary','pending'=>'warning','accepted'=>'info','driver_arrived'=>'info','in_progress'=>'primary','completed'=>'success','cancelled'=>'danger'];
                    $distKm    = rideDistanceKm($r);
                    $comm      = $commissionPct;
                    $netDriver = $r->fare ? round($r->fare * (1 - $comm / 100)) : null;

                    // Duration: actual if completed, estimated from distance otherwise
                    $durationMin = null;
                    $durationActual = false;
                    if ($r->started_at && $r->completed_at) {
                        $durationMin    = (int) \Carbon\Carbon::parse($r->started_at)->diffInMinutes(\Carbon\Carbon::parse($r->completed_at));
                        $durationActual = true;
                    } elseif ($distKm !== null) {
                        $durationMin = (int) ceil(($distKm / 30) * 60); // 30 km/h avg city speed
                    }
                    // Format: "1 Hr 20 Min" or "45 Min" (or "< 1 Min" for a genuine sub-minute actual duration)
                    $durationLabel = null;
                    if ($durationMin !== null && $durationMin === 0 && $durationActual) {
                        $durationLabel = '< 1 Min';
                    } elseif ($durationMin !== null && $durationMin > 0) {
                        $hrs  = intdiv($durationMin, 60);
                        $mins = $durationMin % 60;
                        if ($hrs > 0 && $mins > 0)      $durationLabel = "{$hrs} Hr {$mins} Min";
                        elseif ($hrs > 0)                $durationLabel = "{$hrs} Hour" . ($hrs > 1 ? 's' : '');
                        else                             $durationLabel = "{$mins} Min";
                    }
                    $stops    = $r->stops ?? collect();
                    // Build route popup data
                    $routeData = [
                        'rideId'  => $r->id,
                        'pickup'  => ['address' => $r->pickup_address, 'lat' => $r->pickup_lat, 'lng' => $r->pickup_lng],
                        'stops'   => $stops->map(fn($s) => ['address'=>$s->address,'lat'=>$s->lat,'lng'=>$s->lng,'arrived_at'=>$s->arrived_at?->format('d M H:i')])->values()->all(),
                        'dropoff' => ['address' => $r->dropoff_address, 'lat' => $r->dropoff_lat, 'lng' => $r->dropoff_lng],
                    ];
                @endphp
                <tr>
                    <td>{{ ($rides->currentPage() - 1) * $rides->perPage() + $loop->iteration }}</td>
                    <td>
                        <div>{{ $r->passenger?->name ?? '—' }}</div>
                        @if($r->passenger_name)
                        <small class="text-muted">for {{ $r->passenger_name }}</small>
                        @endif
                    </td>
                    <td>
                        @if($r->driver){{ $r->driver->name }}@else<span class="text-muted">Unassigned</span>@endif
                    </td>
                    <td>
                        <span title="{{ $r->pickup_address }}">{{ \Illuminate\Support\Str::limit($r->pickup_address, 20) }}</span>
                    </td>

                    {{-- Dropoff — always clickable to show route popup --}}
                    <td>
                        <span class="dropoff-trigger text-primary"
                              style="cursor:pointer;text-decoration:underline dotted;"
                              data-route="{{ json_encode($routeData) }}"
                              title="Click to see route">
                            <i class="fas fa-map-marked-alt mr-1"></i>
                            @if($r->dropoff_address)
                                {{ \Illuminate\Support\Str::limit($r->dropoff_address, 20) }}
                            @else
                                <em class="text-muted">TBD</em>
                            @endif
                            @if($stops->count() > 0)
                                <span class="badge badge-warning ml-1">{{ $stops->count() + 1 }} stops</span>
                            @endif
                        </span>
                    </td>

                    <td>
                        <span class="badge badge-{{ $sc[$r->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$r->status)) }}</span>
                    </td>

                    {{-- Distance --}}
                    <td class="text-right">
                        @if($distKm !== null)
                        <span class="text-info font-weight-bold">{{ $distKm }}</span> <small class="text-muted">km</small>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Est. Time --}}
                    <td class="text-center">
                        @if($durationLabel)
                        <span class="{{ $durationActual ? 'text-success' : 'text-info' }}" style="font-size:.85rem;font-weight:600;">
                            <i class="fas fa-clock mr-1" style="font-size:.75rem;"></i>{{ $durationLabel }}
                        </span>
                        @if(!$durationActual)
                        <div><small class="text-muted" style="font-size:.7rem;">estimate</small></div>
                        @endif
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Fare --}}
                    <td class="text-right font-weight-bold">
                        @if($r->fare)
                        {{ number_format($r->fare) }}
                        @if($r->discount_amount > 0)
                        <div><small class="text-success">-{{ number_format($r->discount_amount) }} promo</small></div>
                        @endif
                        @else
                        <span class="text-muted">Metered</span>
                        @endif
                    </td>

                    {{-- Net Driver --}}
                    <td class="text-right">
                        @if($netDriver !== null)
                        <span class="text-success font-weight-bold">{{ number_format($netDriver) }}</span>
                        <div><small class="text-muted">{{ 100 - $comm }}% of fare</small></div>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td style="font-size:.82rem;white-space:nowrap;">
                        {{ $r->created_at->format('j-m-Y') }}<br>
                        <span class="text-muted">{{ $r->created_at->format('H:i:s') }}</span>
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.rides.show', $r->id) }}" class="btn btn-xs btn-primary mr-1" title="View Detail"><i class="fas fa-eye"></i></a>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $r->id }},
                            passenger_id: {{ $r->passenger_id }},
                            driver_id: {{ $r->driver_id ?? 'null' }},
                            pickup_address: @json($r->pickup_address),
                            dropoff_address: @json($r->dropoff_address ?? ''),
                            status: @json($r->status),
                            fare: '{{ $r->fare ?? '' }}',
                            service_type: @json($r->service_type ?? ''),
                            notes: @json($r->notes ?? '')
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.rides.destroy', $r) }}" class="d-inline"
                              onsubmit="return confirm('Delete ride #{{ $r->id }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" class="text-center text-muted py-4">No rides found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center justify-content-between">
        <small class="text-muted">Commission: {{ $commissionPct }}% &nbsp;|&nbsp; Net = Fare × {{ 100 - $commissionPct }}%</small>
        {{ $rides->links() }}
    </div>
</div>

{{-- ─── Stop Route Popup ────────────────────────────────────── --}}
<div class="modal fade" id="stopsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title mb-0"><i class="fas fa-route mr-2"></i>Route — Ride <span id="stopModalRideId"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <ul class="list-unstyled mb-0" id="stopsList"></ul>
            </div>
            <div class="modal-footer py-2">
                <a id="gmapsLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-map-marked-alt mr-1"></i>Open in Google Maps
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ─── Create / Edit Modal ─────────────────────────────────── --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Ride</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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

/* ── Stop route popup ── */
document.querySelectorAll('.dropoff-trigger').forEach(function(el) {
    el.addEventListener('click', function() {
        try {
            var route = JSON.parse(this.getAttribute('data-route'));
            showStopsModal(route);
        } catch(e) {
            console.error('Stops data parse error:', e, this.getAttribute('data-route'));
        }
    });
});

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

    $('#stopsModal').modal('show');
}

/* ── Create / Edit ── */
function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Ride';
    document.getElementById('rideForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('rideForm').reset();
    $('#formModal').modal('show');
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
    $('#formModal').modal('show');
}
</script>
@endpush
