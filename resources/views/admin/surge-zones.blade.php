@extends('admin.layout')
@section('title', 'Surge Zones')
@section('page-title', 'Surge Zones')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <i class="fas fa-bolt text-warning mr-2"></i> Surge Zone Management
        </h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Surge Zone
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Multiplier</th>
                    <th>Center (Lat, Lng)</th>
                    <th>Radius</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($zones as $z)
                <tr>
                    <td>{{ $z->id }}</td>
                    <td>
                        <div class="font-weight-bold">{{ $z->name }}</div>
                        @if($z->description)
                            <small class="text-muted">{{ Str::limit($z->description, 40) }}</small>
                        @endif
                    </td>
                    <td>
                        @php $tc = ['rides'=>'primary','deliveries'=>'warning','both'=>'success']; @endphp
                        <span class="badge badge-{{ $tc[$z->type] ?? 'secondary' }}">
                            {{ ucfirst($z->type) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-danger" style="font-size:.85rem;">
                            x{{ number_format($z->multiplier, 2) }}
                        </span>
                        <small class="text-muted ml-1">+{{ round(($z->multiplier - 1) * 100) }}%</small>
                    </td>
                    <td>
                        <code>{{ number_format($z->center_lat, 5) }}, {{ number_format($z->center_lng, 5) }}</code>
                    </td>
                    <td>{{ $z->radius_km }} km</td>
                    <td>
                        @if($z->starts_at || $z->ends_at)
                            <small>
                                @if($z->starts_at) From: {{ $z->starts_at->format('Y-m-d H:i') }}<br>@endif
                                @if($z->ends_at) Until: {{ $z->ends_at->format('Y-m-d H:i') }}@endif
                            </small>
                        @else
                            <span class="text-muted">Always</span>
                        @endif
                    </td>
                    <td>
                        @if($z->isActiveNow())
                            <span class="badge badge-success"><i class="fas fa-circle mr-1" style="font-size:.5rem;"></i> Active</span>
                        @elseif($z->active)
                            <span class="badge badge-warning">Scheduled</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        {{-- Toggle active --}}
                        <form method="POST" action="{{ route('admin.surge-zones.toggle', $z) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-xs {{ $z->active ? 'btn-warning' : 'btn-success' }} mr-1"
                                title="{{ $z->active ? 'Deactivate' : 'Activate' }}">
                                <i class="fas {{ $z->active ? 'fa-pause' : 'fa-play' }}"></i>
                            </button>
                        </form>

                        {{-- Edit --}}
                        <button class="btn btn-xs btn-info mr-1"
                            data-id="{{ $z->id }}"
                            data-name="{{ $z->name }}"
                            data-description="{{ $z->description ?? '' }}"
                            data-lat="{{ $z->center_lat }}"
                            data-lng="{{ $z->center_lng }}"
                            data-radius="{{ $z->radius_km }}"
                            data-multiplier="{{ $z->multiplier }}"
                            data-type="{{ $z->type }}"
                            data-active="{{ $z->active ? '1' : '0' }}"
                            data-starts="{{ $z->starts_at ? $z->starts_at->format('Y-m-d\TH:i') : '' }}"
                            data-ends="{{ $z->ends_at ? $z->ends_at->format('Y-m-d\TH:i') : '' }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>

                        {{-- Delete --}}
                        <form method="POST" action="{{ route('admin.surge-zones.destroy', $z) }}" class="d-inline"
                              onsubmit="return confirm('Delete surge zone &quot;{{ addslashes($z->name) }}&quot;?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No surge zones defined yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $zones->links() }}</div>
</div>

{{-- Create / Edit Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Surge Zone</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="surgeForm" method="POST" action="{{ route('admin.surge-zones.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">

                    <div class="form-group">
                        <label>Zone Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f-name" class="form-control"
                               placeholder="e.g. Airport Zone, City Centre Rush Hour" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" id="f-description" class="form-control"
                               placeholder="Optional note about this zone">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Center Latitude <span class="text-danger">*</span></label>
                            <input type="number" name="center_lat" id="f-lat" class="form-control"
                                   step="0.0000001" min="-90" max="90" placeholder="11.5625" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Center Longitude <span class="text-danger">*</span></label>
                            <input type="number" name="center_lng" id="f-lng" class="form-control"
                                   step="0.0000001" min="-180" max="180" placeholder="104.9160" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Radius (km) <span class="text-danger">*</span></label>
                            <input type="number" name="radius_km" id="f-radius" class="form-control"
                                   step="0.1" min="0.1" max="100" placeholder="2.5" required>
                            <small class="text-muted">Area covered by this surge zone.</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Surge Multiplier <span class="text-danger">*</span></label>
                            <input type="number" name="multiplier" id="f-multiplier" class="form-control"
                                   step="0.1" min="1.1" max="5.0" placeholder="1.5" required
                                   oninput="updateMultiplierLabel(this.value)">
                            <small class="text-muted" id="multiplier-label">e.g. 1.5 = +50% surge</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Applies To <span class="text-danger">*</span></label>
                            <select name="type" id="f-type" class="form-control" required>
                                <option value="both">🚗📦 Rides &amp; Deliveries</option>
                                <option value="rides">🚗 Rides only</option>
                                <option value="deliveries">📦 Deliveries only</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Starts At <small class="text-muted">(leave blank = immediately)</small></label>
                            <input type="datetime-local" name="starts_at" id="f-starts" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Ends At <small class="text-muted">(leave blank = no expiry)</small></label>
                            <input type="datetime-local" name="ends_at" id="f-ends" class="form-control">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" class="custom-control-input" name="active" id="f-active" value="1" checked>
                            <label class="custom-control-label" for="f-active">Zone is Active</label>
                        </div>
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
const storeUrl   = '{{ route("admin.surge-zones.store") }}';
const updateBase = '/admin/surge-zones/';

function updateMultiplierLabel(val) {
    var pct = Math.round((parseFloat(val || 1) - 1) * 100);
    document.getElementById('multiplier-label').textContent =
        isNaN(pct) ? '' : 'x' + parseFloat(val).toFixed(2) + ' = +' + pct + '% surge';
}

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Surge Zone';
    document.getElementById('surgeForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('surgeForm').reset();
    document.getElementById('f-active').checked = true;
    document.getElementById('multiplier-label').textContent = 'e.g. 1.5 = +50% surge';
    $('#formModal').modal('show');
}

function openEdit(btn) {
    var d = btn.dataset;
    document.getElementById('modalTitle').textContent   = 'Edit Surge Zone #' + d.id;
    document.getElementById('surgeForm').action         = updateBase + d.id;
    document.getElementById('formMethod').value         = 'PUT';
    document.getElementById('f-name').value             = d.name;
    document.getElementById('f-description').value      = d.description;
    document.getElementById('f-lat').value              = d.lat;
    document.getElementById('f-lng').value              = d.lng;
    document.getElementById('f-radius').value           = d.radius;
    document.getElementById('f-multiplier').value       = d.multiplier;
    document.getElementById('f-type').value             = d.type;
    document.getElementById('f-starts').value           = d.starts;
    document.getElementById('f-ends').value             = d.ends;
    document.getElementById('f-active').checked         = d.active === '1';
    updateMultiplierLabel(d.multiplier);
    $('#formModal').modal('show');
}
</script>
@endpush
