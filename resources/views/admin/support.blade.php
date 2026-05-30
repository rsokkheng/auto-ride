@extends('admin.layout')
@section('title', 'Support')
@section('page-title', 'Support Tickets')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Support tickets</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Ticket
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>User</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($t->subject, 30) }}</td>
                    <td>{{ $t->user?->name ?? '—' }}</td>
                    <td>
                        @php $pc = ['low'=>'secondary','medium'=>'info','high'=>'warning','urgent'=>'danger']; @endphp
                        <span class="badge badge-{{ $pc[$t->priority] ?? 'secondary' }}">{{ ucfirst($t->priority) }}</span>
                    </td>
                    <td>
                        @php $sc = ['open'=>'primary','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary']; @endphp
                        <span class="badge badge-{{ $sc[$t->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$t->status)) }}</span>
                    </td>
                    <td>{{ optional($admins->firstWhere('id', $t->assigned_to))->name ?? '—' }}</td>
                    <td>{{ $t->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $t->id }},
                            user_id: {{ $t->user_id }},
                            subject: @json($t->subject),
                            status: @json($t->status),
                            priority: @json($t->priority),
                            assigned_to: {{ $t->assigned_to ?? 'null' }}
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.support.destroy', $t) }}" class="d-inline"
                              onsubmit="return confirm('Delete ticket #{{ $t->id }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No tickets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $tickets->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Ticket</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="ticketForm" method="POST" action="{{ route('admin.support.store') }}">
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
                    <div class="form-group">
                        <label>Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" id="f-subject" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Priority <span class="text-danger">*</span></label>
                            <select name="priority" id="f-priority" class="form-control" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="f-status" class="form-control" required>
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to" id="f-assigned" class="form-control">
                            <option value="">— Unassigned —</option>
                            @foreach($admins as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
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
const storeUrl = '{{ route('admin.support.store') }}';
const updateBase = '/admin/support/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Ticket';
    document.getElementById('ticketForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('ticketForm').reset();
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Ticket #' + d.id;
    document.getElementById('ticketForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-user').value = d.user_id;
    document.getElementById('f-subject').value = d.subject;
    document.getElementById('f-priority').value = d.priority;
    document.getElementById('f-status').value = d.status;
    document.getElementById('f-assigned').value = d.assigned_to || '';
    $('#formModal').modal('show');
}
</script>
@endpush
