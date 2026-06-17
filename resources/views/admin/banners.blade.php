@extends('admin.layout')
@section('title', 'Banners')
@section('page-title', 'Promotional Banners')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-images text-warning mr-2"></i> Banners</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Add Banner</button>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Order</th>
                    <th>Preview</th>
                    <th>Title</th>
                    <th>Target</th>
                    <th>Deeplink</th>
                    <th>Validity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($banners as $b)
                <tr>
                    <td class="text-center"><span class="badge badge-light border">{{ $b->sort_order }}</span></td>
                    <td>
                        <img src="{{ asset('storage/' . $b->image) }}" style="height:50px;width:90px;object-fit:cover;border-radius:4px;">
                    </td>
                    <td class="font-weight-bold">{{ $b->title }}</td>
                    <td>
                        @php $tc = ['all'=>'success','passenger'=>'primary','driver'=>'warning']; @endphp
                        <span class="badge badge-{{ $tc[$b->target_role] ?? 'secondary' }}">{{ ucfirst($b->target_role) }}</span>
                    </td>
                    <td><small class="text-muted">{{ $b->deeplink ?? '—' }}</small></td>
                    <td>
                        <small>
                            @if($b->valid_from || $b->valid_until)
                                {{ $b->valid_from?->format('d M Y') ?? '∞' }} → {{ $b->valid_until?->format('d M Y') ?? '∞' }}
                            @else
                                <span class="text-muted">Always</span>
                            @endif
                        </small>
                    </td>
                    <td>
                        @if($b->active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <button class="btn btn-xs btn-info mr-1"
                            data-banner="{{ e(json_encode([
                                'id'          => $b->id,
                                'title'       => $b->title,
                                'deeplink'    => $b->deeplink ?? '',
                                'target_role' => $b->target_role,
                                'sort_order'  => $b->sort_order,
                                'active'      => $b->active,
                                'valid_from'  => $b->valid_from?->format('Y-m-d\TH:i') ?? '',
                                'valid_until' => $b->valid_until?->format('Y-m-d\TH:i') ?? '',
                            ])) }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.banners.destroy', $b) }}" class="d-inline"
                              onsubmit="return confirm('Delete banner?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No banners yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($banners->hasPages())
    <div class="card-footer">{{ $banners->links() }}</div>
    @endif
</div>

<div class="modal fade" id="bannerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bannerModalTitle">Add Banner</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="bannerForm" method="POST" enctype="multipart/form-data"
                  action="{{ route('admin.banners.store') }}">
                @csrf
                <input type="hidden" name="_method" id="bannerMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="b-title" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Target Audience <span class="text-danger">*</span></label>
                            <select name="target_role" id="b-role" class="form-control" required>
                                <option value="all">All Users</option>
                                <option value="passenger">Passengers only</option>
                                <option value="driver">Drivers only</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Deeplink <small class="text-muted">(e.g. autoride://promo/10)</small></label>
                            <input type="text" name="deeplink" id="b-deeplink" class="form-control" maxlength="255">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Active From</label>
                            <input type="datetime-local" name="valid_from" id="b-from" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Expires At</label>
                            <input type="datetime-local" name="valid_until" id="b-until" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" id="b-order" class="form-control" value="0" min="0">
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" class="custom-control-input" name="active" id="b-active" value="1" checked>
                                <label class="custom-control-label" for="b-active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Banner Image <span class="text-danger" id="imgRequired">*</span> <small class="text-muted">JPG/PNG/WEBP, max 4 MB, recommended 1200×400</small></label>
                        <input type="file" name="image" id="b-image" class="form-control-file" accept="image/*">
                        <div id="imgPreview" class="mt-2" style="display:none;">
                            <img id="currentImg" src="" style="max-height:80px;border-radius:4px;">
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
const storeUrl = '{{ route("admin.banners.store") }}';
const updateBase = '/admin/banners/';

function openCreate() {
    document.getElementById('bannerModalTitle').textContent = 'Add Banner';
    document.getElementById('bannerForm').action = storeUrl;
    document.getElementById('bannerMethod').value = 'POST';
    document.getElementById('bannerForm').reset();
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imgRequired').style.display = '';
    document.getElementById('b-image').required = true;
    $('#bannerModal').modal('show');
}

function openEdit(btn) {
    const b = JSON.parse(btn.getAttribute('data-banner'));
    document.getElementById('bannerModalTitle').textContent = 'Edit Banner';
    document.getElementById('bannerForm').action = updateBase + b.id;
    document.getElementById('bannerMethod').value = 'PUT';
    document.getElementById('b-title').value    = b.title;
    document.getElementById('b-role').value     = b.target_role;
    document.getElementById('b-deeplink').value = b.deeplink;
    document.getElementById('b-from').value     = b.valid_from;
    document.getElementById('b-until').value    = b.valid_until;
    document.getElementById('b-order').value    = b.sort_order;
    document.getElementById('b-active').checked = !!b.active;
    document.getElementById('b-image').required = false;
    document.getElementById('imgRequired').style.display = 'none';
    // Show current image
    const preview = document.getElementById('imgPreview');
    document.getElementById('currentImg').src = '/storage/' + (b.image || '');
    preview.style.display = b.image ? 'block' : 'none';
    $('#bannerModal').modal('show');
}
</script>
@endpush
