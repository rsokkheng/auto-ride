@extends('admin.layout')
@section('title', 'Charging Stations')
@section('page-title', 'Charging Stations')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Charging stations</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Station
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Operator</th>
                    <th>Ports</th>
                    <th>Rating</th>
                    <th>Coordinates</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stations as $s)
                <tr>
                    <td>{{ $s->id }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($s->address, 30) }}</td>
                    <td>{{ $s->operator ?? '—' }}</td>
                    <td><span class="badge badge-info">{{ $s->available_ports }}</span></td>
                    <td>
                        @if($s->rating)
                            <i class="fas fa-star text-warning" style="font-size:.75rem;"></i> {{ number_format($s->rating,1) }}
                        @else —
                        @endif
                    </td>
                    <td style="font-size:.8rem;">{{ number_format($s->latitude,4) }}, {{ number_format($s->longitude,4) }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $s->id }},
                            name: @json($s->name),
                            address: @json($s->address),
                            latitude: '{{ $s->latitude }}',
                            longitude: '{{ $s->longitude }}',
                            available_ports: '{{ $s->available_ports }}',
                            operator: @json($s->operator ?? ''),
                            rating: '{{ $s->rating ?? '' }}',
                            details: @json($s->details ?? '')
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.charging-stations.destroy', $s) }}" class="d-inline"
                              onsubmit="return confirm('Delete station {{ addslashes($s->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No stations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $stations->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Charging Station</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="stationForm" method="POST" action="{{ route('admin.charging-stations.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Address <span class="text-danger">*</span></label>
                        <input type="text" name="address" id="f-address" class="form-control" required>
                    </div>
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
    document.getElementById('modalTitle').textContent = 'Add Charging Station';
    document.getElementById('stationForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('stationForm').reset();
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Station #' + d.id;
    document.getElementById('stationForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-name').value = d.name;
    document.getElementById('f-address').value = d.address;
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
