@extends('admin.layout')
@section('title', 'Vehicles')
@section('page-title', 'Vehicles')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Vehicle fleet</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Vehicle
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Driver</th>
                    <th>Plate</th>
                    <th>Make / Model</th>
                    <th>Year</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vehicles as $v)
                <tr>
                    <td>{{ $v->id }}</td>
                    <td>{{ $v->driver?->name ?? '—' }}</td>
                    <td><strong>{{ $v->license_plate }}</strong></td>
                    <td>{{ $v->make }} {{ $v->model }}</td>
                    <td>{{ $v->year }}</td>
                    <td>{{ ucfirst($v->type) }}</td>
                    <td>{{ $v->capacity }}</td>
                    <td>
                        <span class="badge badge-{{ $v->status === 'active' ? 'success' : ($v->status === 'maintenance' ? 'warning' : 'secondary') }}">
                            {{ ucfirst($v->status) }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $v->id }},
                            user_id: {{ $v->user_id }},
                            license_plate: @json($v->license_plate),
                            make: @json($v->make),
                            model: @json($v->model),
                            year: '{{ $v->year }}',
                            type: @json($v->type),
                            status: @json($v->status),
                            capacity: '{{ $v->capacity }}',
                            details: @json($v->details ?? '')
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.vehicles.destroy', $v) }}" class="d-inline"
                              onsubmit="return confirm('Delete vehicle {{ addslashes($v->license_plate) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No vehicles found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $vehicles->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Vehicle</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="vehicleForm" method="POST" action="{{ route('admin.vehicles.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Driver <span class="text-danger">*</span></label>
                        <select name="user_id" id="f-user" class="form-control" required>
                            <option value="">— Select driver —</option>
                            @foreach($drivers as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>License Plate <span class="text-danger">*</span></label>
                            <input type="text" name="license_plate" id="f-plate" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Year <span class="text-danger">*</span></label>
                            <input type="number" name="year" id="f-year" class="form-control" min="1990" max="{{ date('Y')+1 }}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Make <span class="text-danger">*</span></label>
                            <input type="text" name="make" id="f-make" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Model <span class="text-danger">*</span></label>
                            <input type="text" name="model" id="f-model" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Type <span class="text-danger">*</span></label>
                            <select name="type" id="f-type" class="form-control" required>
                                <option value="electric">Electric</option>
                                <option value="sedan">Sedan</option>
                                <option value="suv">SUV</option>
                                <option value="van">Van</option>
                                <option value="motorcycle">Motorcycle</option>
                                <option value="truck">Truck</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="f-status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Capacity <span class="text-danger">*</span></label>
                            <input type="number" name="capacity" id="f-capacity" class="form-control" min="1" max="50" required>
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
const storeUrl = '{{ route('admin.vehicles.store') }}';
const updateBase = '/admin/vehicles/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Vehicle';
    document.getElementById('vehicleForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('vehicleForm').reset();
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Vehicle';
    document.getElementById('vehicleForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-user').value = d.user_id;
    document.getElementById('f-plate').value = d.license_plate;
    document.getElementById('f-make').value = d.make;
    document.getElementById('f-model').value = d.model;
    document.getElementById('f-year').value = d.year;
    document.getElementById('f-type').value = d.type;
    document.getElementById('f-status').value = d.status;
    document.getElementById('f-capacity').value = d.capacity;
    document.getElementById('f-details').value = d.details;
    $('#formModal').modal('show');
}
</script>
@endpush
