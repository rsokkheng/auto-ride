@extends('admin.layout')
@section('title', 'Promo Events')
@section('page-title', 'Promo Events')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-bullhorn text-warning mr-2"></i> Promo Events</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> New Event</button>
    </div>
    <p class="text-muted px-3 pt-3 mb-0" style="font-size:.85rem;">
        Creating an active event immediately pushes a notification to the target audience's mobile app.
    </p>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Body</th>
                    <th>Target</th>
                    <th>Status</th>
                    <th>Pushed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $e)
                <tr>
                    <td>
                        @if($e->image)
                        <img src="{{ $e->image_url }}" style="height:44px;width:70px;object-fit:cover;border-radius:4px;">
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="font-weight-bold">{{ $e->title }}</td>
                    <td><small>{{ \Illuminate\Support\Str::limit($e->body, 60) }}</small></td>
                    <td>
                        @php $tc = ['all'=>'success','passenger'=>'primary','driver'=>'warning']; @endphp
                        <span class="badge badge-{{ $tc[$e->target_role] ?? 'secondary' }}">{{ ucfirst($e->target_role) }}</span>
                    </td>
                    <td>
                        @if($e->active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @if($e->sent_at)
                            <small class="text-muted" title="{{ $e->sent_at }}"><i class="fas fa-paper-plane text-success mr-1"></i>{{ $e->sent_at->diffForHumans() }}</small>
                        @else
                            <small class="text-muted">Not sent</small>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <button class="btn btn-xs btn-info mr-1"
                            data-event="{{ e(json_encode([
                                'id'          => $e->id,
                                'title'       => $e->title,
                                'body'        => $e->body,
                                'target_role' => $e->target_role,
                                'active'      => $e->active,
                                'image_url'   => $e->image_url,
                            ])) }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.promo-events.destroy', $e) }}" class="d-inline"
                              onsubmit="return confirm('Delete this event?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No promo events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($events->hasPages())
    <div class="card-footer">{{ $events->links() }}</div>
    @endif
</div>

<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle">New Event</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="eventForm" method="POST" enctype="multipart/form-data"
                  action="{{ route('admin.promo-events.store') }}">
                @csrf
                <input type="hidden" name="_method" id="eventMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="e-title" class="form-control" required maxlength="100"
                               placeholder="e.g. Friday Night Party Discount">
                    </div>
                    <div class="form-group">
                        <label>Body <span class="text-danger">*</span></label>
                        <textarea name="body" id="e-body" class="form-control" rows="3" required maxlength="1000"
                                  placeholder="e.g. 20% off all rides this Friday 6pm–midnight!"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Target Audience <span class="text-danger">*</span></label>
                            <select name="target_role" id="e-role" class="form-control" required>
                                <option value="all">All Users</option>
                                <option value="passenger">Passengers only</option>
                                <option value="driver">Drivers only</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 d-flex align-items-end">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" class="custom-control-input" name="active" id="e-active" value="1" checked>
                                <label class="custom-control-label" for="e-active">Active (push immediately on save)</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="e-resend-wrap" style="display:none;">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="resend" value="0">
                            <input type="checkbox" class="custom-control-input" name="resend" id="e-resend" value="1">
                            <label class="custom-control-label" for="e-resend">Re-send push notification with these changes</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Image <small class="text-muted">(optional — JPG/PNG/WEBP, max 4 MB)</small></label>
                        <input type="file" name="image" id="e-image" class="form-control-file" accept="image/*">
                        <div id="e-imgPreview" class="mt-2" style="display:none;">
                            <img id="e-currentImg" src="" style="max-height:70px;border-radius:4px;">
                            <small class="text-muted ml-2">Current image (upload new to replace)</small>
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
const eStoreUrl = '{{ route("admin.promo-events.store") }}';
const eUpdateBase = '/admin/promo-events/';

function openCreate() {
    document.getElementById('eventModalTitle').textContent = 'New Event';
    document.getElementById('eventForm').action = eStoreUrl;
    document.getElementById('eventMethod').value = 'POST';
    document.getElementById('eventForm').reset();
    document.getElementById('e-resend-wrap').style.display = 'none';
    document.getElementById('e-imgPreview').style.display = 'none';
    $('#eventModal').modal('show');
}

function openEdit(btn) {
    const ev = JSON.parse(btn.getAttribute('data-event'));
    document.getElementById('eventModalTitle').textContent = 'Edit Event';
    document.getElementById('eventForm').action = eUpdateBase + ev.id;
    document.getElementById('eventMethod').value = 'PUT';
    document.getElementById('e-title').value  = ev.title;
    document.getElementById('e-body').value   = ev.body;
    document.getElementById('e-role').value   = ev.target_role;
    document.getElementById('e-active').checked = !!ev.active;
    document.getElementById('e-resend').checked = false;
    document.getElementById('e-resend-wrap').style.display = 'block';
    const preview = document.getElementById('e-imgPreview');
    document.getElementById('e-currentImg').src = ev.image_url || '';
    preview.style.display = ev.image_url ? 'block' : 'none';
    $('#eventModal').modal('show');
}
</script>
@endpush
