@extends('admin.layout')
@section('title', 'Safety')
@section('page-title', 'Safety Incidents')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Safety incidents</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Incident
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incidents as $i)
                <tr>
                    <td>{{ $i->id }}</td>
                    <td>{{ $i->user?->name ?? '—' }}</td>
                    <td>
                        @php $tc = ['accident'=>'danger','harassment'=>'warning','theft'=>'dark','other'=>'secondary']; @endphp
                        <span class="badge badge-{{ $tc[$i->incident_type] ?? 'secondary' }}">{{ ucfirst($i->incident_type) }}</span>
                    </td>
                    <td>{{ \Illuminate\Support\Str::limit($i->description, 40) }}</td>
                    <td>
                        @php $sc = ['reported'=>'warning','investigating'=>'info','resolved'=>'success','closed'=>'secondary']; @endphp
                        <span class="badge badge-{{ $sc[$i->status] ?? 'secondary' }}">{{ ucfirst($i->status) }}</span>
                    </td>
                    <td>{{ $i->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $i->id }},
                            user_id: {{ $i->user_id }},
                            incident_type: @json($i->incident_type),
                            description: @json($i->description),
                            status: @json($i->status)
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.safety.destroy', $i) }}" class="d-inline"
                              onsubmit="return confirm('Delete incident #{{ $i->id }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No incidents found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $incidents->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Incident</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="incidentForm" method="POST" action="{{ route('admin.safety.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>User <span class="text-danger">*</span></label>
                        <select name="user_id" id="f-user" class="form-control" required>
                            <option value="">— Select user —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Incident Type <span class="text-danger">*</span></label>
                            <select name="incident_type" id="f-type" class="form-control" required>
                                <option value="accident">Accident</option>
                                <option value="harassment">Harassment</option>
                                <option value="theft">Theft</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="f-status" class="form-control" required>
                                <option value="reported">Reported</option>
                                <option value="investigating">Investigating</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description <span class="text-danger">*</span></label>
                        <textarea name="description" id="f-desc" class="form-control" rows="4" required></textarea>
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
const storeUrl = '{{ route('admin.safety.store') }}';
const updateBase = '/admin/safety/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Incident';
    document.getElementById('incidentForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('incidentForm').reset();
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Incident #' + d.id;
    document.getElementById('incidentForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-user').value = d.user_id;
    document.getElementById('f-type').value = d.incident_type;
    document.getElementById('f-status').value = d.status;
    document.getElementById('f-desc').value = d.description;
    $('#formModal').modal('show');
}
</script>
@endpush
