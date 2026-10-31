@extends('admin.layout')
@section('title', 'Charging Stations')
@section('page-title', 'Charging Stations')

@section('content')

<x-admin.page-toolbar subtitle="EV charging points shown to drivers and passengers in the app.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Add Station</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Total Stations" :value="number_format($stats['stations'])" icon="fa-charging-station" tone="primary"
                       :sub="$stats['operators'] . ' ' . Str::plural('operator', $stats['operators'])" />
    <x-admin.stat-card label="Total Ports" :value="number_format($stats['ports'])" icon="fa-plug" tone="success"
                       :sub="$stats['stations'] ? number_format($stats['ports'] / $stats['stations'], 1) . ' per station' : '—'" />
    <x-admin.stat-card label="Average Rating" :value="$stats['rating'] ? number_format($stats['rating'], 1) . ' / 5' : '—'" icon="fa-star" tone="warning"
                       :sub="'from ' . $stats['rated'] . ' rated ' . Str::plural('station', $stats['rated'])" />
    <x-admin.stat-card label="Phnom Penh Locations" :value="number_format($stats['phnom_penh'])" icon="fa-location-dot" tone="violet"
                       :sub="$stats['stations'] ? round($stats['phnom_penh'] / $stats['stations'] * 100) . '% of all stations' : '—'"
                       :href="route('admin.charging-stations', ['province' => 'Phnom Penh'])" :active="request('province') === 'Phnom Penh'" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.charging-stations')" placeholder="Search name, address or operator"
                            :count="$stations->total()" noun="station">
            <x-admin.filter-select name="province" :options="$provinces" all="All provinces" />
            <x-admin.filter-select name="operator" :options="$operators" all="All operators" />
        </x-admin.filter-bar>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Station</th>
                    <th>Location</th>
                    <th>Operator</th>
                    <th>Ports</th>
                    <th>Rating</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($stations as $s)
                @php
                    $details    = is_array($s->details) ? $s->details : (json_decode((string) $s->details, true) ?: []);
                    $connectors = array_slice((array) ($details['connector_types'] ?? []), 0, 2);
                    $more       = max(0, count((array) ($details['connector_types'] ?? [])) - 2);
                    $maxKw      = collect($details['power_kw'] ?? [])->max();
                    $province   = $s->province();
                    $payload    = [
                        'id' => $s->id, 'name' => $s->name, 'address' => $s->address,
                        'localized' => $s->only(['name_en', 'name_kh', 'name_zh', 'address_en', 'address_kh', 'address_zh']),
                        'latitude' => $s->latitude, 'longitude' => $s->longitude, 'available_ports' => $s->available_ports,
                        'operator' => $s->operator ?? '', 'rating' => $s->rating ?? '', 'details' => $s->getAttributes()['details'] ?? '',
                    ];
                @endphp
                <tr data-station="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}">
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <span class="stat-icon tone-success" style="width:38px;height:38px;font-size:.9rem;"><i class="fas fa-charging-station"></i></span>
                            <div class="tw:min-w-0">
                                <div class="text-truncate" style="max-width:200px;font-weight:600;color:var(--ink-900);" title="{{ $s->name }}">{{ $s->name }}</div>
                                <div class="text-muted tw:text-xs">
                                    #{{ $s->id }}@if($maxKw) · up to {{ rtrim(rtrim(number_format($maxKw, 1), '0'), '.') }} kW @endif
                                    @if(!empty($details['open_hours'])) · {{ $details['open_hours'] }} @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width:210px;" title="{{ $s->address }}">{{ $s->address }}</div>
                        <div class="text-muted tw:text-xs">
                            @if($province)<i class="fas fa-location-dot mr-1"></i>{{ $province }} · @endif
                            <a href="https://www.google.com/maps?q={{ $s->latitude }},{{ $s->longitude }}" target="_blank" rel="noopener" class="text-muted">
                                {{ number_format($s->latitude, 4) }}, {{ number_format($s->longitude, 4) }} <i class="fas fa-arrow-up-right-from-square" style="font-size:.6rem;"></i>
                            </a>
                        </div>
                    </td>
                    <td><div class="text-truncate" style="max-width:150px;" title="{{ $s->operator }}">{{ $s->operator ?? '—' }}</div></td>
                    <td>
                        <span class="badge badge-success"><i class="fas fa-plug mr-1"></i>{{ $s->available_ports }} {{ Str::plural('port', $s->available_ports) }}</span>
                        @if($connectors)
                            <div class="text-muted tw:mt-1 tw:text-xs">{{ implode(' · ', $connectors) }}@if($more) +{{ $more }}@endif</div>
                        @endif
                    </td>
                    <td>
                        @if($s->rating)
                            @php $r = (float) $s->rating; $tone = $r >= 4.5 ? 'success' : ($r >= 4 ? 'warning' : 'secondary'); @endphp
                            <span class="badge badge-{{ $tone }}"><i class="fas fa-star mr-1"></i>{{ number_format($r, 1) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(JSON.parse(this.closest('tr').dataset.station))"
                                             :delete="route('admin.charging-stations.destroy', $s)"
                                             :confirm="'Delete station ' . $s->name . '?'" />
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-charging-station" title="No charging stations yet"
                                   hint="Add the first station with the button above." :clear="route('admin.charging-stations')" />
            @endforelse
            </tbody>
        </table>
    </div>

    <x-admin.pagination :paginator="$stations" />
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create Charging Station</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="stationForm" method="POST" action="{{ route('admin.charging-stations.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <small class="text-muted">(English)</small> <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f-name" class="form-control" required>
                    </div>
                    @include('admin.partials.localized-inputs', ['field' => 'name', 'label' => 'Name', 'idPrefix' => 'f-name', 'maxlength' => 255])
                    <div class="form-group">
                        <label>Address <small class="text-muted">(English)</small> <span class="text-danger">*</span></label>
                        <input type="text" name="address" id="f-address" class="form-control" required>
                    </div>
                    @include('admin.partials.localized-inputs', ['field' => 'address', 'label' => 'Address', 'idPrefix' => 'f-address', 'maxlength' => 255])
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Latitude <span class="text-danger">*</span></label>
                            <input type="number" name="latitude" id="f-lat" class="form-control" step="any" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Longitude <span class="text-danger">*</span></label>
                            <input type="number" name="longitude" id="f-lng" class="form-control" step="any" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Available Ports <span class="text-danger">*</span></label>
                            <input type="number" name="available_ports" id="f-ports" class="form-control" min="0" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Operator</label>
                            <input type="text" name="operator" id="f-operator" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Rating (0–5)</label>
                            <input type="number" name="rating" id="f-rating" class="form-control" min="0" max="5" step="0.1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Details</label>
                        <textarea name="details" id="f-details" class="form-control" rows="2"></textarea>
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
const storeUrl = '{{ route('admin.charging-stations.store') }}';
const updateBase = '/admin/charging-stations/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Create Charging Station';
    document.getElementById('stationForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('stationForm').reset();
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Station #' + d.id;
    document.getElementById('stationForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-name').value = d.localized.name_en || d.name;
    document.getElementById('f-address').value = d.localized.address_en || d.address;
    fillLocalized('f-name', d.localized, 'name');
    fillLocalized('f-address', d.localized, 'address');
    document.getElementById('f-lat').value = d.latitude;
    document.getElementById('f-lng').value = d.longitude;
    document.getElementById('f-ports').value = d.available_ports;
    document.getElementById('f-operator').value = d.operator;
    document.getElementById('f-rating').value = d.rating;
    document.getElementById('f-details').value = d.details;
    $('#formModal').modal('show');
}
</script>
@endpush
