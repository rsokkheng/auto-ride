@extends('admin.layout')
@section('title', 'Airport Zones')
@section('page-title', 'Airport Trip Zones')

@section('content')

<x-admin.page-toolbar subtitle="Airports where trips get an airport surcharge and luggage fee when they start or end inside the zone.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Add Airport</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Airport Zones" :value="number_format($stats['total'])" icon="fa-plane-departure" tone="primary"
                       :href="route('admin.airport-zones')" :active="!request('status')" />
    <x-admin.stat-card label="Active" :value="number_format($stats['active'])" icon="fa-circle-check" tone="success"
                       :href="route('admin.airport-zones', ['status' => 'active'])" :active="request('status') === 'active'" />
    <x-admin.stat-card label="Avg. Surcharge" :value="number_format($stats['avg_surcharge']) . ' ៛'" icon="fa-coins" tone="warning" sub="active zones" />
    <x-admin.stat-card label="Avg. Luggage Fee" :value="number_format($stats['avg_luggage']) . ' ៛'" icon="fa-suitcase-rolling" tone="violet" sub="per bag" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.airport-zones')" placeholder="Search airport name or IATA code" :count="$zones->total()" noun="airport">
            <x-admin.filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" all="All statuses" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Airport</th>
                    <th>Detection Area</th>
                    <th class="text-right">Surcharge</th>
                    <th class="text-right">Luggage / bag</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($zones as $zone)
                <tr>
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <span class="badge badge-primary" style="font-size:.78rem;min-width:46px;letter-spacing:.06em;">{{ $zone->iata_code }}</span>
                            <div class="text-truncate" style="max-width:300px;font-weight:600;color:var(--ink-900);" title="{{ $zone->name }}">{{ $zone->name }}</div>
                        </div>
                    </td>
                    <td>
                        <div>{{ number_format($zone->radius_meters) }} m radius</div>
                        <a class="text-muted tw:text-xs" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $zone->latitude }},{{ $zone->longitude }}">
                            {{ number_format($zone->latitude, 4) }}, {{ number_format($zone->longitude, 4) }} <i class="fas fa-arrow-up-right-from-square" style="font-size:.6rem;"></i>
                        </a>
                    </td>
                    <td class="text-right" style="font-weight:600;color:var(--ink-900);">{{ number_format($zone->surcharge_khr) }} ៛</td>
                    <td class="text-right">{{ number_format($zone->luggage_fee_khr) }} ៛</td>
                    <td><span class="status-pill tone-{{ $zone->active ? 'success' : 'muted' }}">{{ $zone->active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-center">
                        <x-admin.row-actions :edit="'openEdit(' . $zone->id . ')'"
                                             :delete="route('admin.airport-zones.destroy', $zone)" :confirm="'Delete airport zone ' . $zone->name . '?'" />
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-plane-departure" title="No airport zones yet"
                                   hint="Add an airport to charge the airport surcharge automatically." :clear="route('admin.airport-zones')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$zones" />
</div>

{{-- Create Modal --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.airport-zones.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Create Airport Zone</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    @include('admin._partials.airport-zone-fields')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modals --}}
@foreach($zones as $zone)
<div class="modal fade" id="editModal{{ $zone->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.airport-zones.update', $zone) }}" method="POST">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit — {{ $zone->name }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    @include('admin._partials.airport-zone-fields', ['zone' => $zone])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

@endsection

@push('scripts')
<script>
function openCreate() { $('#createModal').modal('show'); }
function openEdit(id)  { $('#editModal' + id).modal('show'); }
</script>
@endpush
