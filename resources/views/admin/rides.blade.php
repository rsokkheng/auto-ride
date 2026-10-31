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
    @php $layout = request('view') === 'cards' ? 'cards' : 'list'; @endphp
    <div class="btn-group" role="group" aria-label="Layout">
        <a href="{{ request()->fullUrlWithQuery(['view' => null, 'page' => null]) }}" class="btn {{ $layout === 'list' ? 'btn-primary' : 'btn-secondary' }}" title="List view"><i class="fas fa-list mr-1"></i> List</a>
        <a href="{{ request()->fullUrlWithQuery(['view' => 'cards', 'page' => null]) }}" class="btn {{ $layout === 'cards' ? 'btn-primary' : 'btn-secondary' }}" title="Card view"><i class="fas fa-grip mr-1"></i> Cards</a>
    </div>
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Create Ride</button>
</x-admin.page-toolbar>

@php
    $tabs = [
        'all'       => ['All Rides',   'fa-route',          'primary'],
        'pending'   => ['In Progress', 'fa-location-arrow', 'info'],
        'completed' => ['Completed',   'fa-flag-checkered', 'success'],
        'cancelled' => ['Cancelled',   'fa-ban',            'danger'],
    ];
    $statusTone   = ['requested' => 'muted', 'pending' => 'warning', 'accepted' => 'info', 'driver_arrived' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
    $statusLabel  = ['requested' => 'Searching driver', 'pending' => 'Pending', 'accepted' => 'Driver on the way', 'driver_arrived' => 'Driver arrived', 'in_progress' => 'On trip', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    $serviceIcon  = ['motorcycle' => 'fa-motorcycle', 'tuk_tuk' => 'fa-taxi', 'standard' => 'fa-car', 'premium' => 'fa-car-side', 'shared' => 'fa-people-group', 'van' => 'fa-van-shuttle'];
    $payLabels    = ['cash' => 'Cash', 'wallet' => 'Wallet', 'aba' => 'ABA', 'wing' => 'Wing', 'card' => 'Card', 'other_online' => 'Online'];
    $initials     = fn (?string $name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '?';
@endphp

<x-admin.stat-grid :cols="5">
    @foreach($tabs as $key => [$label, $icon, $tone])
        <x-admin.stat-card :label="$label" :value="number_format($counts[$key])" :icon="$icon" :tone="$tone"
                           :href="route('admin.rides', array_filter(['tab' => $key, 'search' => $search]))" :active="($tab ?? 'all') === $key" />
    @endforeach
    <x-admin.stat-card label="Ride Revenue" :value="number_format($revenue) . ' ៛'" icon="fa-sack-dollar" tone="violet"
                       :sub="(100 - $commissionPct) . '% paid to drivers'" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-body py-3">
        <x-admin.filter-bar :action="route('admin.rides', $layout === 'cards' ? ['view' => 'cards'] : [])" placeholder="Ride #, passenger or driver" :count="$rides->total()" noun="ride">
            @if($layout === 'cards')<input type="hidden" name="view" value="cards">@endif
            <x-admin.filter-select name="tab" :options="collect($tabs)->except('all')->map(fn ($t) => $t[0])->all()" all="All statuses"
                                   :value="($tab ?? 'all') === 'all' ? '' : $tab" />
            <x-admin.filter-select name="service" :options="$serviceLabels" all="All services" />
            <x-admin.filter-select name="payment" :options="$payLabels" all="All payments" />
            <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" aria-label="From date" title="From" style="min-width:0;width:140px;">
            <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" aria-label="To date" title="To" style="min-width:0;width:140px;">
        </x-admin.filter-bar>
    </div>
</div>

@php
    // Display values shared by the list and card layouts.
    $info = function ($r) use ($serviceLabels, $rideTx, $commissionPct) {
        // Price breakdown. Stored fare = trip price − promo discount + waiting fee; tip and
        // cancellation fee are separate. Fee/driver split comes from the ride's transaction.
        $fare   = (int) $r->fare;
        $disc   = (int) $r->discount_amount;
        $wait   = (int) $r->waiting_fee;
        $tip    = (int) $r->tip_amount;
        $cancel = (int) $r->cancellation_fee;
        $air    = (int) $r->airport_surcharge_khr;
        $surge  = (float) ($r->surge_multiplier ?? 1);
        $tx     = $rideTx[$r->id] ?? null;
        $fee    = $tx ? (int) $tx->platform_fee : (int) round($fare * $commissionPct / 100);
        $company = $tx ? (int) $tx->company_share : 0;
        $driver  = $tx ? (int) $tx->driver_earning : $fare - $fee;
        $price  = [
            'trip'      => $fare + $disc - $wait,
            'surge'     => $surge > 1 ? rtrim(rtrim(number_format($surge, 2), '0'), '.') : null,
            'airport'   => $air,
            'waiting'   => $wait,
            'discount'  => $disc,
            'fare'      => $fare,
            'tip'       => $tip,
            'cancel'    => $cancel,
            'paid'      => $fare + $tip,
            'fee'       => $fee,
            'company'   => $company,
            'companyName' => $r->driver?->company?->name,
            'salary'    => $r->driver?->driver_type === 'employee',
            'driver'    => $driver + $tip,
            'estimated' => ! $tx,
        ];

        $distKm = rideDistanceKm($r);
        $minutes = null; $actual = false;
        if ($r->started_at && $r->completed_at) {
            $minutes = (int) \Carbon\Carbon::parse($r->started_at)->diffInMinutes(\Carbon\Carbon::parse($r->completed_at));
            $actual  = true;
        } elseif ($r->duration_min) {
            $minutes = (int) $r->duration_min;
        }
        $stops = $r->stops ?? collect();

        return [
            'dist'      => $distKm !== null && $distKm >= 0.05 ? rtrim(rtrim(number_format($distKm, 1), '0'), '.') . ' km' : null,
            'time'      => $minutes ? (intdiv($minutes, 60) ? intdiv($minutes, 60) . ' h ' . ($minutes % 60) . ' min' : $minutes . ' min') . ($actual ? '' : ' est.') : null,
            'stops'     => $stops->count(),
            'passenger' => $r->passenger?->name ?? $r->passenger_name ?? 'Guest',
            'service'   => $serviceLabels[$r->service_type] ?? ucfirst(str_replace('_', ' ', (string) $r->service_type)),
            'price'     => $price,
            'stopList'  => $stops,
            'route'     => json_encode([
                'rideId'    => $r->id,
                'pickup'    => ['address' => $r->pickup_address, 'lat' => $r->pickup_lat, 'lng' => $r->pickup_lng],
                'stops'     => $stops->map(fn ($s) => ['address' => $s->address, 'lat' => $s->lat, 'lng' => $s->lng, 'arrived_at' => $s->arrived_at?->format('d M H:i')])->values()->all(),
                'dropoff'   => ['address' => $r->dropoff_address, 'lat' => $r->dropoff_lat, 'lng' => $r->dropoff_lng],
                'distance'  => $distKm !== null && $distKm >= 0.05 ? rtrim(rtrim(number_format($distKm, 1), '0'), '.') . ' km' : null,
                'time'      => $minutes ? (intdiv($minutes, 60) ? intdiv($minutes, 60) . ' h ' . ($minutes % 60) . ' min' : $minutes . ' min') . ($actual ? '' : ' (estimate)') : null,
                'fare'      => $r->fare ? number_format($r->fare) . ' ៛' : null,
                'price'     => $price,
                'service'   => $serviceLabels[$r->service_type] ?? ucfirst(str_replace('_', ' ', (string) $r->service_type)),
                'status'    => $r->status,
                'passenger' => trim(($r->passenger?->name ?? $r->passenger_name ?? 'Guest') . ' · ' . ($r->passenger_phone ?? $r->passenger?->phone ?? ''), ' ·'),
                'driver'    => $r->driver ? trim($r->driver->name . ' · ' . ($r->vehicle?->license_plate ?? $r->driver->phone ?? ''), ' ·') : null,
                'times'     => array_filter([
                    'Requested' => $r->created_at?->format('d M Y, H:i'),
                    'Accepted'  => $r->accepted_at ? \Carbon\Carbon::parse($r->accepted_at)->format('d M, H:i') : null,
                    'Started'   => $r->started_at ? \Carbon\Carbon::parse($r->started_at)->format('d M, H:i') : null,
                    'Completed' => $r->completed_at ? \Carbon\Carbon::parse($r->completed_at)->format('d M, H:i') : null,
                    'Cancelled' => $r->cancelled_at ? \Carbon\Carbon::parse($r->cancelled_at)->format('d M, H:i') : null,
                ]),
            ]),
            'edit'      => json_encode([
                'id' => $r->id, 'passenger_id' => $r->passenger_id, 'driver_id' => $r->driver_id,
                'pickup_address' => $r->pickup_address, 'dropoff_address' => $r->dropoff_address ?? '',
                'status' => $r->status, 'fare' => $r->fare ?? '', 'service_type' => $r->service_type ?? '', 'notes' => $r->notes ?? '',
            ]),
        ];
    };
@endphp

@if($rides->isEmpty())
    <div class="card"><table class="table mb-0"><tbody>
        <x-admin.empty-row colspan="1" icon="fa-route" title="No rides yet" hint="Rides booked in the app will appear here." :clear="route('admin.rides')" />
    </tbody></table></div>

@elseif($layout === 'list')
{{-- ── List ─────────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Ride / Status</th>
                    <th>Route</th>
                    <th>Passenger / Driver</th>
                    <th>Price</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rides as $r)
                @php $i = $info($r); @endphp
                <tr data-ride="{{ $i['edit'] }}">
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <span class="stat-icon tone-primary" style="width:34px;height:34px;font-size:.82rem;"><i class="fas {{ $serviceIcon[$r->service_type] ?? 'fa-car' }}"></i></span>
                            <div>
                                <a href="{{ route('admin.rides.show', $r->id) }}" style="font-weight:600;color:var(--ink-900);">#{{ $r->id }}</a>
                                <div class="text-muted tw:text-xs">{{ $i['service'] }} · {{ $r->created_at->format('d M, H:i') }}</div>
                                <div class="tw:mt-1"><span class="status-pill tone-{{ $statusTone[$r->status] ?? 'muted' }}">{{ $statusLabel[$r->status] ?? ucfirst(str_replace('_', ' ', $r->status)) }}</span></div>
                            </div>
                        </div>
                    </td>
                    <td style="white-space:normal;min-width:240px;width:32%;">
                        <div class="tw:flex tw:items-start tw:gap-2">
                            <div class="trip is-full tw:flex-1">
                                <div class="trip-stop"><span class="trip-label">Pickup</span><span>{{ $r->pickup_address ?: '—' }}</span></div>
                                @foreach($i['stopList'] as $n => $stop)
                                    <div class="trip-stop is-stop"><span class="trip-label">Stop {{ $n + 1 }}@if($stop->arrived_at) · arrived {{ $stop->arrived_at->format('H:i') }}@endif</span><span>{{ $stop->address }}</span></div>
                                @endforeach
                                <div class="trip-stop is-drop"><span class="trip-label">Drop-off</span><span>{{ $r->dropoff_address ?: 'Not set' }}</span></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-icon tw:shrink-0" title="Show route on map" data-toggle="tooltip"
                                    data-route="{{ $i['route'] }}" onclick="showStopsModal(JSON.parse(this.dataset.route))">
                                <i class="fas fa-map-location-dot"></i>
                            </button>
                        </div>
                        @if($i['dist'] || $i['time'] || $i['stops'])
                            <div class="trip-meta" style="padding-left:16px;">
                                @if($i['dist'])<span><i class="fas fa-road"></i>{{ $i['dist'] }}</span>@endif
                                @if($i['time'])<span><i class="far fa-clock"></i>{{ $i['time'] }}</span>@endif
                                @if($i['stops'])<span><i class="fas fa-location-dot"></i>{{ $i['stops'] }} {{ Str::plural('stop', $i['stops']) }}</span>@endif
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width:150px;color:var(--ink-900);"><i class="fas fa-user mr-2 text-muted" style="font-size:.7rem;width:10px;"></i>{{ $i['passenger'] }}</div>
                        <div class="text-truncate tw:text-xs tw:mt-1" style="max-width:150px;">
                            @if($r->driver)
                                <i class="fas fa-id-card mr-2" style="font-size:.7rem;width:10px;color:var(--success);"></i><a href="{{ route('admin.drivers.show', $r->driver) }}">{{ $r->driver->name }}</a>
                                @if($r->vehicle)<span class="text-muted"> · {{ $r->vehicle->license_plate }}</span>@endif
                            @else
                                <i class="fas fa-user-clock mr-2 text-muted" style="font-size:.7rem;width:10px;"></i><span class="text-muted">No driver yet</span>
                            @endif
                        </div>
                    </td>
                    <td>@include('admin.partials.ride-fare-lines', ['p' => $i['price'], 'r' => $r, 'payLabels' => $payLabels])</td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(JSON.parse(this.closest('tr').dataset.ride))"
                                             :delete="route('admin.rides.destroy', $r)" :confirm="'Delete ride #' . $r->id . '? This cannot be undone.'">
                            <a href="{{ route('admin.rides.show', $r->id) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="View details" data-toggle="tooltip"><i class="fas fa-eye"></i></a>
                        </x-admin.row-actions>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$rides" />
</div>

@else
{{-- ── Cards ────────────────────────────────────────────────────────── --}}
<div class="order-grid">
    @foreach($rides as $r)
        @php $i = $info($r); @endphp
        <article class="order-card" data-ride="{{ $i['edit'] }}">
            <div class="order-card-head">
                <span class="stat-icon tone-primary" style="width:40px;height:40px;font-size:.95rem;"><i class="fas {{ $serviceIcon[$r->service_type] ?? 'fa-car' }}"></i></span>
                <div class="tw:min-w-0 tw:flex-1">
                    <a href="{{ route('admin.rides.show', $r->id) }}" class="order-id">Ride #{{ $r->id }}</a>
                    <div class="order-sub">{{ $i['service'] }} · {{ $r->created_at->format('d M Y, H:i') }}</div>
                </div>
                <span class="status-pill tone-{{ $statusTone[$r->status] ?? 'muted' }}">{{ $statusLabel[$r->status] ?? ucfirst(str_replace('_', ' ', $r->status)) }}</span>
            </div>
            <div class="order-card-body">
                <div class="tw:flex tw:items-start tw:gap-2">
                    <div class="trip is-full tw:flex-1">
                        <div class="trip-stop"><span class="trip-label">Pickup</span><span>{{ $r->pickup_address ?: '—' }}</span></div>
                        @foreach($i['stopList'] as $n => $stop)
                            <div class="trip-stop is-stop"><span class="trip-label">Stop {{ $n + 1 }}</span><span>{{ $stop->address }}</span></div>
                        @endforeach
                        <div class="trip-stop is-drop"><span class="trip-label">Drop-off</span><span>{{ $r->dropoff_address ?: 'Not set' }}</span></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-icon tw:shrink-0" title="Show route on map" data-toggle="tooltip"
                            data-route="{{ $i['route'] }}" onclick="showStopsModal(JSON.parse(this.dataset.route))">
                        <i class="fas fa-map-location-dot"></i>
                    </button>
                </div>
                @if($i['dist'] || $i['time'] || $i['stops'])
                    <div class="trip-meta" style="padding-left:16px;">
                        @if($i['dist'])<span><i class="fas fa-road"></i>{{ $i['dist'] }}</span>@endif
                        @if($i['time'])<span><i class="far fa-clock"></i>{{ $i['time'] }}</span>@endif
                        @if($i['stops'])<span><i class="fas fa-location-dot"></i>{{ $i['stops'] }} {{ Str::plural('stop', $i['stops']) }}</span>@endif
                    </div>
                @endif
            </div>

            <div class="order-card-people">
                <div class="who">
                    <span class="avatar-chip">{{ $initials($i['passenger']) }}</span>
                    <div class="tw:min-w-0"><div class="who-name">{{ $i['passenger'] }}</div><div class="who-sub">Passenger</div></div>
                </div>
                <i class="fas fa-arrow-right text-muted" style="font-size:.7rem;"></i>
                <div class="who">
                    @if($r->driver)
                        <span class="avatar-chip" style="background:var(--success-soft);color:var(--success-ink);">{{ $initials($r->driver->name) }}</span>
                        <div class="tw:min-w-0"><div class="who-name">{{ $r->driver->name }}</div><div class="who-sub">{{ $r->vehicle?->license_plate ?? 'Driver' }}</div></div>
                    @else
                        <span class="avatar-chip" style="background:var(--ink-100);color:var(--ink-400);"><i class="fas fa-user-clock"></i></span>
                        <div class="who-sub">No driver yet</div>
                    @endif
                </div>
            </div>
            <div class="order-card-foot" style="align-items:flex-end;">
                <div class="tw:flex-1">@include('admin.partials.ride-fare-lines', ['p' => $i['price'], 'r' => $r, 'payLabels' => $payLabels])</div>
                <x-admin.row-actions edit="openEdit(JSON.parse(this.closest('.order-card').dataset.ride))"
                                     :delete="route('admin.rides.destroy', $r)" :confirm="'Delete ride #' . $r->id . '? This cannot be undone.'">
                    <a href="{{ route('admin.rides.show', $r->id) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="View details" data-toggle="tooltip"><i class="fas fa-eye"></i></a>
                </x-admin.row-actions>
            </div>
        </article>
    @endforeach
</div>
@if($rides->hasPages())
    <div class="card tw:mt-4"><x-admin.pagination :paginator="$rides" /></div>
@endif
@endif

{{-- ─── Route popup ─────────────────────────────────────────── --}}
<div class="modal fade" id="stopsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><i class="fas fa-route mr-2" style="color:var(--primary);"></i>Route · Ride <span id="stopModalRideId"></span></h5>
                    <div class="text-muted tw:text-xs tw:mt-1" id="routeService"></div>
                </div>
                <span id="routeStatus" class="ml-auto mr-3"></span>
                <button type="button" class="close" onclick="hideModal(this.closest('.modal').id)" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="tw:grid tw:grid-cols-3 tw:gap-2 tw:mb-3" id="routeSummary"></div>
                <div id="routeMapWrap" style="border:1px solid var(--ink-200);border-radius:12px;overflow:hidden;background:var(--ink-50);" class="tw:mb-3">
                    <iframe id="routeMap" title="Route map" loading="lazy" style="width:100%;height:280px;border:0;display:block;" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div class="tw:grid tw:gap-4 tw:md:grid-cols-5">
                    <div class="tw:md:col-span-3">
                        <div class="stat-label tw:mb-2">Stops</div>
                        <ul class="list-unstyled mb-0" id="stopsList"></ul>
                    </div>
                    <div class="tw:md:col-span-2">
                        <div class="stat-label tw:mb-2">Price</div>
                        <div id="routePrice" class="fare-lines tw:mb-3" style="font-size:.84rem;"></div>
                        <div class="stat-label tw:mb-2">People</div>
                        <div id="routePeople" class="tw:text-sm tw:mb-3"></div>
                        <div class="stat-label tw:mb-2">Timeline</div>
                        <div id="routeTimes" class="tw:text-sm"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideModal(this.closest('.modal').id)">Close</button>
                <a id="gmapsLink" href="#" target="_blank" rel="noopener" class="btn btn-primary"><i class="fas fa-map-location-dot mr-1"></i>Open in Google Maps</a>
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
function escapeHtml(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

var RIDE_STATUS = {
    requested: ['Searching driver', 'muted'], pending: ['Pending', 'warning'], accepted: ['Driver on the way', 'info'],
    driver_arrived: ['Driver arrived', 'info'], in_progress: ['On trip', 'primary'], completed: ['Completed', 'success'], cancelled: ['Cancelled', 'danger'],
};

function showStopsModal(route) {
    document.getElementById('stopModalRideId').textContent = '#' + route.rideId;
    document.getElementById('routeService').textContent = route.service || '';
    var st = RIDE_STATUS[route.status] || [route.status || '', 'muted'];
    document.getElementById('routeStatus').innerHTML = route.status ? '<span class="status-pill tone-' + st[1] + '">' + escapeHtml(st[0]) + '</span>' : '';

    // Summary tiles
    var tiles = [['fa-road', 'Distance', route.distance], ['fa-clock', 'Time', route.time], ['fa-coins', 'Fare', route.fare]];
    document.getElementById('routeSummary').innerHTML = tiles.map(function (t) {
        return '<div style="border:1px solid var(--ink-200);border-radius:10px;padding:.55rem .75rem;">'
            + '<div class="stat-label"><i class="fas ' + t[0] + ' mr-1"></i>' + t[1] + '</div>'
            + '<div style="font-weight:600;color:var(--ink-900);">' + escapeHtml(t[2] || '—') + '</div></div>';
    }).join('');

    // Points in order
    var points = [{ type: 'pickup', label: 'Pickup', address: route.pickup.address, lat: route.pickup.lat, lng: route.pickup.lng }];
    (route.stops || []).forEach(function (s, i) {
        points.push({ type: 'stop', label: 'Stop ' + (i + 1), address: s.address, lat: s.lat, lng: s.lng, arrived_at: s.arrived_at });
    });
    if (route.dropoff && (route.dropoff.address || route.dropoff.lat)) {
        points.push({ type: 'dropoff', label: 'Drop-off', address: route.dropoff.address, lat: route.dropoff.lat, lng: route.dropoff.lng });
    }

    var colors = { pickup: 'var(--success)', stop: 'var(--warning)', dropoff: 'var(--danger)' };
    var icons  = { pickup: 'fa-location-dot', stop: 'fa-map-pin', dropoff: 'fa-flag-checkered' };
    document.getElementById('stopsList').innerHTML = points.map(function (p, i) {
        var last = i === points.length - 1;
        return '<li style="display:flex;gap:.75rem;">'
            + '<div style="display:flex;flex-direction:column;align-items:center;width:28px;flex-shrink:0;">'
            +   '<span style="width:28px;height:28px;border-radius:50%;background:' + colors[p.type] + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;"><i class="fas ' + icons[p.type] + '"></i></span>'
            +   (last ? '' : '<span style="width:2px;flex:1;background:var(--ink-200);margin:3px 0;"></span>')
            + '</div>'
            + '<div style="padding-bottom:' + (last ? '0' : '.9rem') + ';min-width:0;">'
            +   '<div class="trip-label" style="color:' + colors[p.type] + ';">' + escapeHtml(p.label)
            +     (p.arrived_at ? ' <span class="badge badge-success ml-1">arrived ' + escapeHtml(p.arrived_at) + '</span>' : '') + '</div>'
            +   '<div style="font-weight:500;color:var(--ink-900);word-break:break-word;">' + (p.address ? escapeHtml(p.address) : '<em class="text-muted">Not set</em>') + '</div>'
            +   (p.lat ? '<div class="text-muted tw:text-xs">' + escapeHtml(p.lat) + ', ' + escapeHtml(p.lng) + '</div>' : '')
            + '</div></li>';
    }).join('');

    // Price breakdown
    var p = route.price || {}, k = function (v) { return Number(v || 0).toLocaleString('en-US') + ' ៛'; }, lines = [];
    if (p.fare) {
        lines.push(['', 'Trip fare', k(p.trip)]);
        if (p.surge || p.airport) lines.push(['sub', [p.surge ? 'incl. surge ×' + p.surge : '', p.airport ? 'incl. airport ' + k(p.airport) : ''].filter(Boolean).join(' · '), '']);
        if (p.waiting)  lines.push(['', 'Waiting fee', '+' + k(p.waiting)]);
        if (p.discount) lines.push(['minus', 'Promo discount', '−' + k(p.discount)]);
        lines.push(['total', 'Fare', k(p.fare)]);
        if (p.tip) { lines.push(['', 'Tip', '+' + k(p.tip)]); lines.push(['total', 'Customer paid', k(p.paid)]); }
    }
    if (p.cancel) lines.push(['total', 'Cancellation fee', k(p.cancel)]);
    if (p.fare) {
        lines.push(['sep']);
        lines.push(['split', 'Platform fee' + (p.estimated ? ' (est.)' : ''), k(p.fee)]);
        if (p.company) lines.push(['split', (p.companyName ? p.companyName : 'Company') + ' share', k(p.company)]);
        lines.push(['driver', p.salary ? 'Driver (on salary)' : 'Driver earns', k(p.driver)]);
    }
    document.getElementById('routePrice').innerHTML = lines.length
        ? lines.map(function (l) {
            return l[0] === 'sep' ? '<div class="sep"></div>'
                : '<div class="fl ' + l[0] + '"><span>' + escapeHtml(l[1]) + '</span><span>' + escapeHtml(l[2]) + '</span></div>';
        }).join('')
        : '<span class="text-muted">Metered — not priced yet</span>';

    // People + timeline
    document.getElementById('routePeople').innerHTML =
        '<div><i class="fas fa-user mr-2 text-muted"></i>' + escapeHtml(route.passenger || '—') + '</div>'
        + '<div class="tw:mt-1"><i class="fas fa-id-card mr-2" style="color:var(--success);"></i>' + (route.driver ? escapeHtml(route.driver) : '<span class="text-muted">No driver yet</span>') + '</div>';
    var times = route.times || {};
    document.getElementById('routeTimes').innerHTML = Object.keys(times).length
        ? Object.keys(times).map(function (k) {
            return '<div class="tw:flex tw:justify-between tw:gap-3" style="padding:.2rem 0;border-bottom:1px dashed var(--ink-100);"><span class="text-muted">' + escapeHtml(k) + '</span><span>' + escapeHtml(times[k]) + '</span></div>';
        }).join('')
        : '<span class="text-muted">—</span>';

    // Map: embedded directions (no API key) + Google Maps link
    var coords = points.filter(function (p) { return p.lat && p.lng; }).map(function (p) { return p.lat + ',' + p.lng; });
    var map = document.getElementById('routeMap'), wrap = document.getElementById('routeMapWrap'), link = document.getElementById('gmapsLink');
    if (coords.length >= 2) {
        var embed = 'https://maps.google.com/maps?output=embed&saddr=' + encodeURIComponent(coords[0])
            + '&daddr=' + coords.slice(1).map(encodeURIComponent).join('+to:');
        map.src = embed; wrap.style.display = '';
        link.href = 'https://www.google.com/maps/dir/' + coords.join('/'); link.style.display = '';
    } else if (coords.length === 1) {
        map.src = 'https://maps.google.com/maps?output=embed&q=' + encodeURIComponent(coords[0]); wrap.style.display = '';
        link.href = 'https://www.google.com/maps?q=' + coords[0]; link.style.display = '';
    } else {
        map.removeAttribute('src'); wrap.style.display = 'none'; link.style.display = 'none';
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
