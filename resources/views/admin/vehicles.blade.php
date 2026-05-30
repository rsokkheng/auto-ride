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
                    <th></th>
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
                    <td style="width:50px;">
                        @if($v->primary_image_url)
                            <img src="{{ $v->primary_image_url }}" alt=""
                                 style="width:44px;height:44px;border-radius:8px;object-fit:cover;">
                        @else
                            <div style="width:44px;height:44px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-car" style="color:#94a3b8;"></i>
                            </div>
                        @endif
                    </td>
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
                        <button class="btn btn-xs btn-info mr-1"
                            data-id="{{ $v->id }}"
                            data-user="{{ $v->user_id }}"
                            data-plate="{{ $v->license_plate }}"
                            data-make="{{ $v->make }}"
                            data-model="{{ $v->model }}"
                            data-year="{{ $v->year }}"
                            data-type="{{ $v->type }}"
                            data-status="{{ $v->status }}"
                            data-capacity="{{ $v->capacity }}"
                            data-details="{{ $v->details ?? '' }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>

                        {{-- Photos button --}}
                        <button class="btn btn-xs btn-warning mr-1"
                            data-id="{{ $v->id }}"
                            data-images="{{ e(json_encode($v->image_urls)) }}"
                            data-paths="{{ e(json_encode($v->images ?? [])) }}"
                            onclick="openPhotos(this)"
                            title="Manage Photos">
                            <i class="fas fa-images"></i>
                            @if(count($v->images ?? []) > 0)
                                <span class="ml-1">{{ count($v->images) }}</span>
                            @endif
                        </button>

                        <form method="POST" action="{{ route('admin.vehicles.destroy', $v) }}" class="d-inline"
                              onsubmit="return confirm('Delete vehicle {{ addslashes($v->license_plate) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No vehicles found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $vehicles->links() }}</div>
</div>

{{-- Edit / Create Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Vehicle</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="vehicleForm" method="POST" action="{{ route('admin.vehicles.store') }}" enctype="multipart/form-data">
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
                                <option value="electric">⚡ Electric</option>
                                <option value="sedan">🚗 Sedan</option>
                                <option value="suv">🚙 SUV</option>
                                <option value="van">🚐 Van</option>
                                <option value="motorcycle">🏍️ Motorcycle</option>
                                <option value="truck">🚚 Truck</option>
                                <option value="tuk_tuk">🛺 Tuk-tuk</option>
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
                    <div class="form-group" id="imageUploadGroup">
                        <label>Vehicle Photos <small class="text-muted">(jpeg/png/webp, max 3 MB each, up to 5)</small></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="images[]" id="f-images"
                                   accept="image/jpeg,image/png,image/webp" multiple
                                   onchange="previewVehicleImages(this)">
                            <label class="custom-file-label" for="f-images">Choose images...</label>
                        </div>
                        <div id="images-preview" class="row mt-2"></div>
                        <small id="edit-images-note" class="text-muted" style="display:none;">
                            New images will be added to existing ones (max 5 total). Use the
                            <i class="fas fa-images"></i> button to remove existing photos.
                        </small>
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

{{-- Photos Modal --}}
<div class="modal fade" id="photosModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-images mr-1"></i> Vehicle Photos</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">

                {{-- Current photos grid --}}
                <div id="photos-grid" class="row mb-3"></div>
                <p id="no-photos" class="text-muted text-center" style="display:none;">No photos yet. Upload the first one below.</p>

                {{-- Upload new photo --}}
                <hr>
                <form id="photoUploadForm" method="POST" action="" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label class="font-weight-bold"><i class="fas fa-upload mr-1"></i> Upload New Photo</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="image" id="f-photo"
                                   accept="image/jpeg,image/png,image/webp" required onchange="previewNewPhoto(this)">
                            <label class="custom-file-label" for="f-photo">Choose image (jpeg/png/webp, max 3 MB)</label>
                        </div>
                    </div>
                    <div id="new-photo-preview" class="mb-2"></div>
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Upload Photo
                    </button>
                </form>
                <small class="text-muted">Maximum 5 photos per vehicle.</small>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Hidden delete form (reused for each image) --}}
<form id="deletePhotoForm" method="POST" action="" style="display:none;">
    @csrf @method('DELETE')
    <input type="hidden" name="path" id="deletePhotoPath">
</form>
@endsection

@push('scripts')
<script>
const storeUrl  = '{{ route("admin.vehicles.store") }}';
const updateBase = '/admin/vehicles/';

function resetImageField() {
    document.getElementById('images-preview').innerHTML = '';
    var input = document.getElementById('f-images');
    input.value = '';
    var label = input.nextElementSibling;
    if (label) label.textContent = 'Choose images...';
}

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Vehicle';
    document.getElementById('vehicleForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('vehicleForm').reset();
    document.getElementById('edit-images-note').style.display = 'none';
    resetImageField();
    $('#formModal').modal('show');
}

function openEdit(btn) {
    var d = btn.dataset;
    document.getElementById('modalTitle').textContent = 'Edit Vehicle #' + d.id;
    document.getElementById('vehicleForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-user').value     = d.user;
    document.getElementById('f-plate').value    = d.plate;
    document.getElementById('f-make').value     = d.make;
    document.getElementById('f-model').value    = d.model;
    document.getElementById('f-year').value     = d.year;
    document.getElementById('f-type').value     = d.type;
    document.getElementById('f-status').value   = d.status;
    document.getElementById('f-capacity').value = d.capacity;
    document.getElementById('f-details').value  = d.details;
    document.getElementById('edit-images-note').style.display = 'block';
    resetImageField();
    $('#formModal').modal('show');
}

function openPhotos(btn) {
    var vehicleId = btn.dataset.id;
    var imageUrls = JSON.parse(btn.dataset.images);
    var paths     = JSON.parse(btn.dataset.paths);
    var uploadUrl = '/admin/vehicles/' + vehicleId + '/images';

    document.getElementById('photoUploadForm').action = uploadUrl;
    document.getElementById('deletePhotoForm').action  = uploadUrl;
    document.getElementById('new-photo-preview').innerHTML = '';

    var grid = document.getElementById('photos-grid');
    grid.innerHTML = '';

    if (imageUrls.length === 0) {
        document.getElementById('no-photos').style.display = 'block';
    } else {
        document.getElementById('no-photos').style.display = 'none';
        imageUrls.forEach(function(url, i) {
            grid.innerHTML += '<div class="col-md-4 col-6 mb-3" id="photo-item-' + i + '">' +
                '<div class="position-relative">' +
                '<img src="' + url + '" style="width:100%;height:140px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">' +
                '<button type="button" class="btn btn-xs btn-danger position-absolute" ' +
                'style="top:6px;right:6px;" onclick="deletePhoto(\'' + paths[i] + '\', \'' + uploadUrl + '\')">' +
                '<i class="fas fa-trash"></i></button>' +
                '</div>' +
                '</div>';
        });
    }

    $('#photosModal').modal('show');
}

function deletePhoto(path, actionUrl) {
    if (!confirm('Delete this photo?')) return;
    var form = document.getElementById('deletePhotoForm');
    form.action = actionUrl;
    document.getElementById('deletePhotoPath').value = path;
    form.submit();
}

function previewVehicleImages(input) {
    var preview = document.getElementById('images-preview');
    preview.innerHTML = '';
    if (input.files && input.files.length) {
        Array.from(input.files).slice(0, 5).forEach(function(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML += '<div class="col-4 col-md-3 mb-2">' +
                    '<img src="' + e.target.result + '" style="width:100%;height:80px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">' +
                    '</div>';
            };
            reader.readAsDataURL(file);
        });
        var label = input.nextElementSibling;
        if (label) label.textContent = input.files.length + ' file(s) selected';
    }
}

function previewNewPhoto(input) {
    var preview = document.getElementById('new-photo-preview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" style="max-height:120px;border-radius:8px;border:2px solid #e63946;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
    // Update custom file label
    var label = input.nextElementSibling;
    if (label) label.textContent = input.files[0].name;
}
</script>
@endpush
