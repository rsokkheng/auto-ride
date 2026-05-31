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
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Multiplier</th>
                    <th>Radius</th>
                    <th>Center</th>
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
                        <span class="badge badge-{{ $tc[$z->type] ?? 'secondary' }}">{{ ucfirst($z->type) }}</span>
                    </td>
                    <td>
                        <span class="badge badge-danger" style="font-size:.85rem;">
                            x{{ number_format($z->multiplier, 2) }}
                        </span>
                        <small class="text-muted ml-1">+{{ round(($z->multiplier - 1) * 100) }}%</small>
                    </td>
                    <td>{{ $z->radius_km }} km</td>
                    <td><code style="font-size:.75rem;">{{ number_format($z->center_lat,5) }}, {{ number_format($z->center_lng,5) }}</code></td>
                    <td>
                        <div style="font-size:.8rem;">
                            @if($z->schedule_label === 'Always')
                                <span class="text-muted">Always on</span>
                            @else
                                <i class="fas fa-clock text-info mr-1"></i>{{ $z->schedule_label }}
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($z->isActiveNow())
                            <span class="badge badge-success"><i class="fas fa-circle mr-1" style="font-size:.45rem;"></i> Active now</span>
                        @elseif($z->active)
                            <span class="badge badge-warning">Scheduled</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <form method="POST" action="{{ route('admin.surge-zones.toggle', $z) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-xs {{ $z->active ? 'btn-warning' : 'btn-success' }} mr-1"
                                    title="{{ $z->active ? 'Deactivate' : 'Activate' }}">
                                <i class="fas {{ $z->active ? 'fa-pause' : 'fa-play' }}"></i>
                            </button>
                        </form>

                        <button class="btn btn-xs btn-info mr-1"
                            data-zone="{{ e(json_encode([
                                'id'                  => $z->id,
                                'name'                => $z->name,
                                'description'         => $z->description ?? '',
                                'center_lat'          => $z->center_lat,
                                'center_lng'          => $z->center_lng,
                                'radius_km'           => $z->radius_km,
                                'multiplier'          => $z->multiplier,
                                'type'                => $z->type,
                                'active'              => $z->active,
                                'starts_at'           => $z->starts_at ? $z->starts_at->format('Y-m-d\TH:i') : '',
                                'ends_at'             => $z->ends_at   ? $z->ends_at->format('Y-m-d\TH:i')   : '',
                                'schedule_days'       => $z->schedule_days ?? [],
                                'schedule_start_time' => $z->schedule_start_time ? substr($z->schedule_start_time,0,5) : '',
                                'schedule_end_time'   => $z->schedule_end_time   ? substr($z->schedule_end_time,0,5)   : '',
                            ])) }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>

                        <form method="POST" action="{{ route('admin.surge-zones.destroy', $z) }}" class="d-inline"
                              onsubmit="return confirm('Delete &quot;{{ addslashes($z->name) }}&quot;?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No surge zones yet.</td></tr>
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

                    {{-- Basic info --}}
                    <div class="form-group">
                        <label>Zone Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f-name" class="form-control"
                               placeholder="e.g. Airport Rush, City Centre Peak" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" id="f-description" class="form-control"
                               placeholder="Optional note">
                    </div>

                    {{-- Location --}}
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label>Center Latitude <span class="text-danger">*</span></label>
                            <input type="number" name="center_lat" id="f-lat" class="form-control"
                                   step="0.0000001" min="-90" max="90" placeholder="11.5625" required>
                        </div>
                        <div class="form-group col-md-5">
                            <label>Center Longitude <span class="text-danger">*</span></label>
                            <input type="number" name="center_lng" id="f-lng" class="form-control"
                                   step="0.0000001" min="-180" max="180" placeholder="104.9160" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Radius (km) <span class="text-danger">*</span></label>
                            <input type="number" name="radius_km" id="f-radius" class="form-control"
                                   step="0.1" min="0.1" max="100" placeholder="2.5" required>
                        </div>
                    </div>

                    {{-- Multiplier + Type --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Surge Multiplier <span class="text-danger">*</span></label>
                            <input type="number" name="multiplier" id="f-multiplier" class="form-control"
                                   step="0.1" min="1.1" max="5.0" placeholder="1.5" required
                                   oninput="updateMultiplierLabel(this.value)">
                            <small class="text-muted" id="multiplier-label">e.g. 1.5 = +50% surge</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Applies To <span class="text-danger">*</span></label>
                            <select name="type" id="f-type" class="form-control" required>
                                <option value="both">🚗📦 Rides &amp; Deliveries</option>
                                <option value="rides">🚗 Rides only</option>
                                <option value="deliveries">📦 Deliveries only</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">
                    <p class="font-weight-bold mb-2"><i class="fas fa-calendar-alt text-primary mr-2"></i>Schedule</p>
                    <small class="text-muted d-block mb-3">
                        Leave all schedule fields blank for an <strong>always-on</strong> zone.
                        Mix date windows with recurring time slots as needed.
                    </small>

                    {{-- One-time date window --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label><i class="fas fa-calendar-day mr-1"></i> Active From <small class="text-muted">(one-time date)</small></label>
                            <input type="datetime-local" name="starts_at" id="f-starts" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label><i class="fas fa-calendar-times mr-1"></i> Expires At <small class="text-muted">(one-time date)</small></label>
                            <input type="datetime-local" name="ends_at" id="f-ends" class="form-control">
                        </div>
                    </div>

                    {{-- Recurring days --}}
                    <div class="form-group">
                        <label><i class="fas fa-repeat mr-1"></i> Recurring Days <small class="text-muted">(leave unchecked = every day)</small></label>
                        <div class="d-flex flex-wrap gap-2 mt-1" style="gap:8px;">
                            @foreach(['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6] as $label => $val)
                            <div class="custom-control custom-checkbox custom-control-inline">
                                <input type="checkbox" class="custom-control-input schedule-day"
                                       name="schedule_days[]" id="day-{{ $val }}" value="{{ $val }}">
                                <label class="custom-control-label" for="day-{{ $val }}">{{ $label }}</label>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1" onclick="setDays([1,2,3,4,5])">Weekdays</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1" onclick="setDays([0,6])">Weekends</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setDays([])">Clear</button>
                        </div>
                    </div>

                    {{-- Recurring time window --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label><i class="fas fa-clock mr-1"></i> Time From <small class="text-muted">(daily, 24h)</small></label>
                            <input type="time" name="schedule_start_time" id="f-start-time" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label><i class="fas fa-clock mr-1"></i> Time Until <small class="text-muted">(daily, 24h)</small></label>
                            <input type="time" name="schedule_end_time" id="f-end-time" class="form-control">
                        </div>
                    </div>

                    {{-- Quick presets --}}
                    <div class="mb-3">
                        <small class="text-muted mr-2">Quick presets:</small>
                        <button type="button" class="btn btn-xs btn-outline-info mr-1" onclick="applyPreset('morning')">🌅 Morning Rush (07:00–09:00)</button>
                        <button type="button" class="btn btn-xs btn-outline-info mr-1" onclick="applyPreset('evening')">🌆 Evening Rush (17:00–20:00)</button>
                        <button type="button" class="btn btn-xs btn-outline-info mr-1" onclick="applyPreset('night')">🌙 Late Night (22:00–05:00)</button>
                        <button type="button" class="btn btn-xs btn-outline-info" onclick="applyPreset('weekend')">🎉 Weekend All Day</button>
                    </div>

                    {{-- Active toggle --}}
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

const PRESETS = {
    morning : { days: [1,2,3,4,5], start: '07:00', end: '09:00' },
    evening : { days: [1,2,3,4,5], start: '17:00', end: '20:00' },
    night   : { days: [],          start: '22:00', end: '05:00' },
    weekend : { days: [0,6],       start: '',      end: ''       },
};

function applyPreset(key) {
    const p = PRESETS[key];
    if (!p) return;
    setDays(p.days);
    document.getElementById('f-start-time').value = p.start;
    document.getElementById('f-end-time').value   = p.end;
}

function setDays(days) {
    document.querySelectorAll('.schedule-day').forEach(cb => {
        cb.checked = days.includes(parseInt(cb.value));
    });
}

function updateMultiplierLabel(val) {
    const pct = Math.round((parseFloat(val || 1) - 1) * 100);
    document.getElementById('multiplier-label').textContent =
        isNaN(pct) ? '' : 'x' + parseFloat(val).toFixed(2) + ' = +' + pct + '% surge';
}

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Surge Zone';
    document.getElementById('surgeForm').action       = storeUrl;
    document.getElementById('formMethod').value       = 'POST';
    document.getElementById('surgeForm').reset();
    setDays([]);
    document.getElementById('f-active').checked = true;
    document.getElementById('multiplier-label').textContent = 'e.g. 1.5 = +50% surge';
    $('#formModal').modal('show');
}

function openEdit(btn) {
    const z = JSON.parse(btn.getAttribute('data-zone'));

    document.getElementById('modalTitle').textContent   = 'Edit Surge Zone #' + z.id;
    document.getElementById('surgeForm').action         = updateBase + z.id;
    document.getElementById('formMethod').value         = 'PUT';
    document.getElementById('f-name').value             = z.name;
    document.getElementById('f-description').value      = z.description;
    document.getElementById('f-lat').value              = z.center_lat;
    document.getElementById('f-lng').value              = z.center_lng;
    document.getElementById('f-radius').value           = z.radius_km;
    document.getElementById('f-multiplier').value       = z.multiplier;
    document.getElementById('f-type').value             = z.type;
    document.getElementById('f-starts').value           = z.starts_at;
    document.getElementById('f-ends').value             = z.ends_at;
    document.getElementById('f-start-time').value       = z.schedule_start_time;
    document.getElementById('f-end-time').value         = z.schedule_end_time;
    document.getElementById('f-active').checked         = !!z.active;

    setDays(z.schedule_days || []);
    updateMultiplierLabel(z.multiplier);
    $('#formModal').modal('show');
}
</script>
@endpush
