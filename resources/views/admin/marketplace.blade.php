@extends('admin.layout')
@section('title', 'Marketplace')
@section('page-title', 'Marketplace')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-store text-primary mr-2"></i> Marketplace Items</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Item
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Images</th>
                    <th>Title</th>
                    <th>Seller</th>
                    <th>Type</th>
                    <th>Price (USD)</th>
                    <th>Rent/day (USD)</th>
                    <th>Condition</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>
                        @if($item->images->count())
                            <div class="d-flex flex-wrap" style="gap:3px">
                                @foreach($item->images->take(3) as $img)
                                    <img src="{{ Storage::url($img->path) }}"
                                         style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6">
                                @endforeach
                                @if($item->images->count() > 3)
                                    <span class="badge badge-secondary align-self-center">+{{ $item->images->count() - 3 }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>{{ \Illuminate\Support\Str::limit($item->title, 28) }}</td>
                    <td>
                        @if($item->entry_type === 'guest')
                            <span class="badge badge-warning">Guest</span><br>
                            <small>{{ $item->guest_name }}</small><br>
                            <small class="text-muted">{{ $item->guest_phone }}</small>
                        @else
                            {{ $item->seller?->name ?? '—' }}
                        @endif
                    </td>
                    <td>
                        @if($item->type === 'both')
                            <span class="badge badge-success">Sale</span>
                            <span class="badge badge-info">Rent</span>
                        @elseif($item->type === 'rent')
                            <span class="badge badge-info">Rent</span>
                        @else
                            <span class="badge badge-success">Sale</span>
                        @endif
                    </td>
                    <td>{{ $item->price ? '$'.number_format($item->price, 2) : '—' }}</td>
                    <td>{{ $item->rent_rate ? '$'.number_format($item->rent_rate, 2) : '—' }}</td>
                    <td>{{ ucfirst($item->condition) }}</td>
                    <td>
                        <span class="badge badge-{{ $item->available ? 'success' : 'secondary' }}">
                            {{ $item->available ? 'Yes' : 'No' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-outline-primary mr-1"
                                onclick='openEdit({{ $item->id }}, @json($item->load("images")))'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.marketplace.destroy', $item) }}"
                              class="d-inline" onsubmit="return confirm('Delete this item and all its images?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $items->links() }}</div>
</div>

{{-- ── Modal ──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="modalTitle">Add Item</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <form id="itemForm" method="POST" action="{{ route('admin.marketplace.store') }}"
                  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">

                {{-- Scrollable body --}}
                <div class="modal-body" style="max-height:72vh;overflow-y:auto">

                    {{-- ── Entry Type ───────────────────────────────────── --}}
                    <div class="form-group">
                        <label class="d-block">Entry By</label>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" id="btn-entry-user" class="btn btn-primary" onclick="setEntryType('user')">
                                <i class="fas fa-user mr-1"></i> Registered User
                            </button>
                            <button type="button" id="btn-entry-guest" class="btn btn-outline-secondary" onclick="setEntryType('guest')">
                                <i class="fas fa-user-clock mr-1"></i> Guest
                            </button>
                        </div>
                        <input type="hidden" name="entry_type" id="f-entry-type" value="user">
                    </div>

                    {{-- ── User Seller (entry_type = user) ─────────────────── --}}
                    <div id="sellerSection">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Seller <span class="text-danger">*</span></label>
                                <select name="seller_id" id="f-seller" class="form-control">
                                    <option value="">— Select seller —</option>
                                    @foreach($sellers as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Vehicle</label>
                                <select name="vehicle_id" id="f-vehicle" class="form-control">
                                    <option value="">— None —</option>
                                    @foreach($vehicles as $v)
                                        <option value="{{ $v->id }}">{{ $v->make }} {{ $v->model }} ({{ $v->license_plate }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- ── Guest Info (entry_type = guest) ─────────────────── --}}
                    <div id="guestSection" style="display:none">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Guest Name <span class="text-danger">*</span></label>
                                <input type="text" name="guest_name" id="f-guest-name" class="form-control" maxlength="100" placeholder="Full name">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Guest Phone <span class="text-danger">*</span></label>
                                <input type="text" name="guest_phone" id="f-guest-phone" class="form-control" maxlength="20" placeholder="+855...">
                            </div>
                        </div>
                    </div>

                    {{-- ── Title / Description ─────────────────────────── --}}
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="f-title" class="form-control" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="f-desc" class="form-control" rows="2"></textarea>
                    </div>

                    {{-- ── Listing Type ─────────────────────────────────── --}}
                    <div class="form-group">
                        <label>Listing Type <span class="text-danger">*</span></label>
                        <div class="d-flex" style="gap:24px">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="f-is-sale" name="is_sale" value="1" onchange="togglePriceFields()">
                                <label class="custom-control-label font-weight-bold" for="f-is-sale">For Sale</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="f-is-rent" name="is_rent" value="1" onchange="togglePriceFields()">
                                <label class="custom-control-label font-weight-bold" for="f-is-rent">For Rent</label>
                            </div>
                        </div>
                        <small id="typeError" class="text-danger d-none">Please select at least one listing type.</small>
                    </div>

                    {{-- ── Price / Rent Rate (shown based on type selection) ── --}}
                    <div class="form-row">
                        <div class="form-group col-md-6" id="priceWrap" style="display:none">
                            <label>Sale Price (USD) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                <input type="number" name="price" id="f-price" class="form-control" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="form-group col-md-6" id="rentWrap" style="display:none">
                            <label>Rent Rate ($/day) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                <input type="number" name="rent_rate" id="f-rent" class="form-control" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    {{-- ── Condition / Available ────────────────────────── --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Condition <span class="text-danger">*</span></label>
                            <select name="condition" id="f-condition" class="form-control" required>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 d-flex align-items-end pb-2">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="available" id="f-available" value="1" checked>
                                <label class="custom-control-label" for="f-available">Available</label>
                            </div>
                        </div>
                    </div>

                    {{-- ── Images ───────────────────────────────────────── --}}
                    <div class="card card-outline card-primary mt-1">
                        <div class="card-header py-2">
                            <h3 class="card-title"><i class="fas fa-images mr-1"></i> Product Images</h3>
                            <div class="card-tools">
                                <small class="text-muted">JPG · PNG · WEBP · max 5 MB each · up to 10 files</small>
                            </div>
                        </div>
                        <div class="card-body">

                            {{-- Upload button (always visible) --}}
                            <div class="mb-3">
                                <label class="btn btn-outline-primary btn-sm mb-0" for="f-images">
                                    <i class="fas fa-upload mr-1"></i> Choose Images
                                </label>
                                <input type="file"
                                       id="f-images"
                                       name="images[]"
                                       multiple
                                       accept="image/jpeg,image/png,image/webp"
                                       style="display:none"
                                       onchange="handleImageSelect(this)">
                                <span id="fileCount" class="ml-2 text-muted small"></span>
                            </div>

                            {{-- New image previews --}}
                            <div id="newPreviews" class="d-flex flex-wrap" style="gap:8px;min-height:10px"></div>

                            {{-- Existing images (edit mode) --}}
                            <div id="existingImagesWrap" class="d-none">
                                <hr class="my-2">
                                <p class="text-muted small mb-2 font-weight-bold">EXISTING IMAGES — click × to remove</p>
                                <div id="existingImages" class="d-flex flex-wrap" style="gap:8px"></div>
                            </div>

                        </div>
                    </div>

                </div>{{-- end modal-body --}}

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show position-fixed" style="bottom:20px;right:20px;z-index:9999;min-width:260px">
    {{ session('success') }}<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif

@endsection

@push('scripts')
<script>
const storeUrl      = '{{ route('admin.marketplace.store') }}';
const updateBase    = '/admin/marketplace/';
const imgDeleteBase = '/admin/marketplace-images/';
const csrf          = document.querySelector('meta[name="csrf-token"]').content;

// ── Image selection & preview ────────────────────────────────────────────────

function handleImageSelect(input) {
    const files   = Array.from(input.files);
    const counter = document.getElementById('fileCount');
    const preview = document.getElementById('newPreviews');

    counter.textContent = files.length ? files.length + ' file(s) selected' : '';
    preview.innerHTML   = '';

    files.forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.style.cssText = 'position:relative;width:80px;height:80px';
            div.innerHTML = `
                <img src="${e.target.result}"
                     style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:2px solid #3b82f6">
                <span style="position:absolute;top:2px;left:3px;background:rgba(0,0,0,.55);color:#fff;font-size:9px;padding:1px 4px;border-radius:3px">
                    New
                </span>`;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

// ── Existing images (edit) ───────────────────────────────────────────────────

function renderExistingImages(images) {
    const wrap      = document.getElementById('existingImagesWrap');
    const container = document.getElementById('existingImages');
    container.innerHTML = '';

    if (!images || !images.length) {
        wrap.classList.add('d-none');
        return;
    }
    wrap.classList.remove('d-none');

    images.forEach(img => {
        const div = document.createElement('div');
        div.id = 'img-wrap-' + img.id;
        div.style.cssText = 'position:relative;width:80px;height:80px';
        div.innerHTML = `
            <img src="/storage/${img.path}"
                 style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6">
            <button type="button"
                    onclick="deleteExistingImage(${img.id})"
                    title="Remove"
                    style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;
                           background:#dc3545;border:2px solid #fff;color:#fff;font-size:11px;
                           line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center">
                &times;
            </button>`;
        container.appendChild(div);
    });
}

function deleteExistingImage(id) {
    if (!confirm('Remove this image?')) return;
    fetch(imgDeleteBase + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(() => {
        const el = document.getElementById('img-wrap-' + id);
        if (el) el.remove();
        // hide section if no images left
        if (!document.getElementById('existingImages').children.length) {
            document.getElementById('existingImagesWrap').classList.add('d-none');
        }
    })
    .catch(() => alert('Failed to delete image. Please try again.'));
}

// ── Entry type (user vs guest) ───────────────────────────────────────────────

function setEntryType(type) {
    document.getElementById('f-entry-type').value = type;

    const isGuest = type === 'guest';
    document.getElementById('sellerSection').style.display = isGuest ? 'none' : '';
    document.getElementById('guestSection').style.display  = isGuest ? ''     : 'none';

    document.getElementById('f-seller').required      = !isGuest;
    document.getElementById('f-guest-name').required  = isGuest;
    document.getElementById('f-guest-phone').required = isGuest;

    document.getElementById('btn-entry-user').className  = isGuest
        ? 'btn btn-outline-secondary' : 'btn btn-primary';
    document.getElementById('btn-entry-guest').className = isGuest
        ? 'btn btn-warning'           : 'btn btn-outline-secondary';
}

// ── Listing type toggles ─────────────────────────────────────────────────────

function togglePriceFields() {
    const isSale = document.getElementById('f-is-sale').checked;
    const isRent = document.getElementById('f-is-rent').checked;

    const priceWrap = document.getElementById('priceWrap');
    const rentWrap  = document.getElementById('rentWrap');
    const errMsg    = document.getElementById('typeError');

    priceWrap.style.display = isSale ? '' : 'none';
    rentWrap.style.display  = isRent ? '' : 'none';

    document.getElementById('f-price').required = isSale;
    document.getElementById('f-rent').required  = isRent;

    errMsg.classList.toggle('d-none', isSale || isRent);
}

function setTypeCheckboxes(type) {
    const isSale = type === 'sale' || type === 'both';
    const isRent = type === 'rent' || type === 'both';
    document.getElementById('f-is-sale').checked = isSale;
    document.getElementById('f-is-rent').checked = isRent;
    togglePriceFields();
}

// Guard form submit so at least one type must be selected
document.getElementById('itemForm').addEventListener('submit', function (e) {
    const isSale = document.getElementById('f-is-sale').checked;
    const isRent = document.getElementById('f-is-rent').checked;
    if (!isSale && !isRent) {
        e.preventDefault();
        document.getElementById('typeError').classList.remove('d-none');
        document.getElementById('f-is-sale').focus();
    }
});

// ── Reset helpers ────────────────────────────────────────────────────────────

function resetImageSection() {
    document.getElementById('f-images').value       = '';
    document.getElementById('newPreviews').innerHTML = '';
    document.getElementById('fileCount').textContent = '';
    document.getElementById('existingImages').innerHTML = '';
    document.getElementById('existingImagesWrap').classList.add('d-none');
}

// ── Open modals ──────────────────────────────────────────────────────────────

function openCreate() {
    document.getElementById('modalTitle').textContent  = 'Add Item';
    document.getElementById('itemForm').action         = storeUrl;
    document.getElementById('formMethod').value        = 'POST';
    document.getElementById('itemForm').reset();
    document.getElementById('f-available').checked     = true;
    document.getElementById('f-is-sale').checked       = false;
    document.getElementById('f-is-rent').checked       = false;
    setEntryType('user');
    togglePriceFields();
    resetImageSection();
    $('#formModal').modal('show');
}

function openEdit(id, d) {
    document.getElementById('modalTitle').textContent   = 'Edit Item #' + id;
    document.getElementById('itemForm').action          = updateBase + id;
    document.getElementById('formMethod').value         = 'PUT';
    document.getElementById('f-title').value            = d.title       || '';
    document.getElementById('f-desc').value             = d.description || '';
    document.getElementById('f-price').value            = d.price       || '';
    document.getElementById('f-rent').value             = d.rent_rate   || '';
    document.getElementById('f-condition').value        = d.condition   || 'good';
    document.getElementById('f-available').checked      = !!d.available;

    const entryType = d.entry_type || 'user';
    setEntryType(entryType);
    if (entryType === 'user') {
        document.getElementById('f-seller').value  = d.seller_id  || '';
        document.getElementById('f-vehicle').value = d.vehicle_id || '';
    } else {
        document.getElementById('f-guest-name').value  = d.guest_name  || '';
        document.getElementById('f-guest-phone').value = d.guest_phone || '';
    }

    setTypeCheckboxes(d.type || 'sale');
    resetImageSection();
    renderExistingImages(d.images || []);
    $('#formModal').modal('show');
}
</script>
@endpush
