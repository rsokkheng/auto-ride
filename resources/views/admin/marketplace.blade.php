@extends('admin.layout')
@section('title', 'Marketplace')
@section('page-title', 'Marketplace')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Marketplace items</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Item
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Seller</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Rent/day</th>
                    <th>Condition</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($item->title, 28) }}</td>
                    <td>{{ $item->seller?->name ?? '—' }}</td>
                    <td><span class="badge badge-{{ $item->type === 'rent' ? 'info' : 'success' }}">{{ ucfirst($item->type) }}</span></td>
                    <td>{{ $item->price ? '$'.number_format($item->price,2) : '—' }}</td>
                    <td>{{ $item->rent_rate ? '$'.number_format($item->rent_rate,2) : '—' }}</td>
                    <td>{{ ucfirst($item->condition) }}</td>
                    <td>
                        <span class="badge badge-{{ $item->available ? 'success' : 'secondary' }}">
                            {{ $item->available ? 'Yes' : 'No' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $item->id }},
                            seller_id: {{ $item->seller_id }},
                            vehicle_id: {{ $item->vehicle_id ?? 'null' }},
                            title: @json($item->title),
                            description: @json($item->description ?? ''),
                            type: @json($item->type),
                            price: '{{ $item->price ?? '' }}',
                            rent_rate: '{{ $item->rent_rate ?? '' }}',
                            available: {{ $item->available ? 'true' : 'false' }},
                            condition: @json($item->condition)
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.marketplace.destroy', $item) }}" class="d-inline"
                              onsubmit="return confirm('Delete this item?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $items->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Item</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="itemForm" method="POST" action="{{ route('admin.marketplace.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Seller <span class="text-danger">*</span></label>
                            <select name="seller_id" id="f-seller" class="form-control" required>
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
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="f-title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="f-desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Type <span class="text-danger">*</span></label>
                            <select name="type" id="f-type" class="form-control" required>
                                <option value="rent">Rent</option>
                                <option value="sale">Sale</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Price ($)</label>
                            <input type="number" name="price" id="f-price" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Rent Rate ($/day)</label>
                            <input type="number" name="rent_rate" id="f-rent" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
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
                        <div class="form-group col-md-6 d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" name="available" id="f-available" value="1">
                                <label class="custom-control-label" for="f-available">Available</label>
                            </div>
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
const storeUrl = '{{ route('admin.marketplace.store') }}';
const updateBase = '/admin/marketplace/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Item';
    document.getElementById('itemForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('itemForm').reset();
    document.getElementById('f-available').checked = false;
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Item #' + d.id;
    document.getElementById('itemForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-seller').value = d.seller_id;
    document.getElementById('f-vehicle').value = d.vehicle_id || '';
    document.getElementById('f-title').value = d.title;
    document.getElementById('f-desc').value = d.description;
    document.getElementById('f-type').value = d.type;
    document.getElementById('f-price').value = d.price;
    document.getElementById('f-rent').value = d.rent_rate;
    document.getElementById('f-condition').value = d.condition;
    document.getElementById('f-available').checked = d.available;
    $('#formModal').modal('show');
}
</script>
@endpush
