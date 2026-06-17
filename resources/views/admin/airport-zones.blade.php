@extends('admin.layout')
@section('title', 'Airport Zones')
@section('page-title', 'Airport Trip Zones')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-plane text-primary mr-2"></i> Airport Zones</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Add Zone</button>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Airport</th>
                    <th>IATA</th>
                    <th>Coordinates</th>
                    <th>Radius</th>
                    <th>Surcharge</th>
                    <th>Luggage/bag</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($zones as $zone)
                <tr>
                    <td><strong>{{ $zone->name }}</strong></td>
                    <td><span class="badge badge-primary">{{ $zone->iata_code }}</span></td>
                    <td class="small text-muted">{{ $zone->latitude }}, {{ $zone->longitude }}</td>
                    <td>{{ number_format($zone->radius_meters) }} m</td>
                    <td>{{ number_format($zone->surcharge_khr) }} ៛</td>
                    <td>{{ number_format($zone->luggage_fee_khr) }} ៛</td>
                    <td>
                        <span class="badge badge-{{ $zone->active ? 'success' : 'secondary' }}">
                            {{ $zone->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-outline-primary" onclick="openEdit({{ $zone->id }})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('admin.airport-zones.destroy', $zone) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this airport zone?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No airport zones defined yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($zones->hasPages())
    <div class="card-footer">{{ $zones->links() }}</div>
    @endif
</div>

{{-- Create Modal --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.airport-zones.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Airport Zone</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
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

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show position-fixed" style="bottom:20px;right:20px;z-index:9999">
    {{ session('success') }}<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif
@endsection

@push('scripts')
<script>
function openCreate() { $('#createModal').modal('show'); }
function openEdit(id)  { $('#editModal' + id).modal('show'); }
</script>
@endpush
