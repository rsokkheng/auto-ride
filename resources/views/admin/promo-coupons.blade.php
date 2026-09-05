@extends('admin.layout')
@section('title', 'Promo Coupons')
@section('page-title', 'Promo Coupons')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-ticket-alt text-success mr-2"></i> Promo Coupons</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> New Coupon</button>
    </div>
    <p class="text-muted px-3 pt-3 mb-0" style="font-size:.85rem;">
        Codes customers enter at checkout to get a discount on a ride, delivery, or moving order.
    </p>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th>Service</th>
                    <th>Usage</th>
                    <th>Window</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($coupons as $c)
                <tr>
                    <td>
                        <div class="font-weight-bold">{{ $c->code }}</div>
                        @if($c->description)
                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($c->description, 50) }}</small>
                        @endif
                    </td>
                    <td>
                        @if($c->type === 'percent')
                            <span class="badge badge-info">{{ $c->value }}%</span>
                            @if($c->max_discount)
                                <br><small class="text-muted">up to ៛{{ number_format($c->max_discount) }}</small>
                            @endif
                        @else
                            <span class="badge badge-info">៛{{ number_format($c->value) }}</span>
                        @endif
                        @if($c->min_order > 0)
                            <br><small class="text-muted">min ៛{{ number_format($c->min_order) }}</small>
                        @endif
                    </td>
                    <td><span class="badge badge-secondary">{{ ucfirst($c->service_type) }}</span></td>
                    <td>
                        <small>
                            {{ $c->used_count }}{{ $c->usage_limit ? ' / ' . $c->usage_limit : ' / ∞' }} used
                            <br class="d-none d-md-inline">
                            <span class="text-muted">{{ $c->per_user_limit }} per user</span>
                        </small>
                    </td>
                    <td>
                        <small class="text-muted">
                            @if($c->starts_at || $c->expires_at)
                                {{ $c->starts_at?->format('d M Y') ?? '—' }} → {{ $c->expires_at?->format('d M Y') ?? '∞' }}
                            @else
                                Always
                            @endif
                        </small>
                    </td>
                    <td>
                        @if($c->active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <button class="btn btn-xs btn-info mr-1"
                            data-coupon="{{ e(json_encode([
                                'id'             => $c->id,
                                'code'           => $c->code,
                                'description'    => $c->description,
                                'type'           => $c->type,
                                'value'          => $c->value,
                                'min_order'      => $c->min_order,
                                'max_discount'   => $c->max_discount,
                                'usage_limit'    => $c->usage_limit,
                                'per_user_limit' => $c->per_user_limit,
                                'service_type'   => $c->service_type,
                                'active'         => $c->active,
                                'starts_at'      => optional($c->starts_at)->format('Y-m-d\TH:i'),
                                'expires_at'     => optional($c->expires_at)->format('Y-m-d\TH:i'),
                            ])) }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.promo-coupons.destroy', $c) }}" class="d-inline"
                              onsubmit="return confirm('Delete coupon {{ $c->code }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No promo coupons yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($coupons->hasPages())
    <div class="card-footer">{{ $coupons->links() }}</div>
    @endif
</div>

<div class="modal fade" id="couponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="couponModalTitle">New Coupon</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="couponForm" method="POST" action="{{ route('admin.promo-coupons.store') }}">
                @csrf
                <input type="hidden" name="_method" id="couponMethod" value="POST">
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="c-code" class="form-control text-uppercase" required maxlength="32"
                                   style="letter-spacing:1px;font-weight:700;" placeholder="e.g. NEWUSER50">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Description <small class="text-muted">(optional)</small></label>
                            <input type="text" name="description" id="c-description" class="form-control" maxlength="255"
                                   placeholder="e.g. 50% off for new users">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Discount Type <span class="text-danger">*</span></label>
                            <select name="type" id="c-type" class="form-control" required onchange="toggleMaxDiscount()">
                                <option value="percent">Percent (%)</option>
                                <option value="fixed">Fixed Amount (៛)</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label id="c-value-label">Value <span class="text-danger">*</span></label>
                            <input type="number" name="value" id="c-value" class="form-control" required min="1">
                        </div>
                        <div class="form-group col-md-4" id="c-maxdiscount-wrap">
                            <label>Max Discount (៛) <small class="text-muted">(percent cap)</small></label>
                            <input type="number" name="max_discount" id="c-maxdiscount" class="form-control" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Minimum Order (៛)</label>
                            <input type="number" name="min_order" id="c-minorder" class="form-control" min="0" value="0">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Usage Limit <small class="text-muted">(blank = unlimited)</small></label>
                            <input type="number" name="usage_limit" id="c-usagelimit" class="form-control" min="1">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Per-User Limit <span class="text-danger">*</span></label>
                            <input type="number" name="per_user_limit" id="c-peruserlimit" class="form-control" required min="1" value="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Applies To <span class="text-danger">*</span></label>
                            <select name="service_type" id="c-servicetype" class="form-control" required>
                                <option value="all">All services</option>
                                <option value="rides">Rides only</option>
                                <option value="deliveries">Deliveries only</option>
                                <option value="moving">Moving only</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Starts At <small class="text-muted">(optional)</small></label>
                            <input type="datetime-local" name="starts_at" id="c-startsat" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Expires At <small class="text-muted">(optional)</small></label>
                            <input type="datetime-local" name="expires_at" id="c-expiresat" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" class="custom-control-input" name="active" id="c-active" value="1" checked>
                            <label class="custom-control-label" for="c-active">Active (customers can redeem this code immediately)</label>
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
const cStoreUrl  = '{{ route("admin.promo-coupons.store") }}';
const cUpdateBase = '{{ url("admin/promo-coupons") }}/';

function toggleMaxDiscount() {
    const isPercent = document.getElementById('c-type').value === 'percent';
    document.getElementById('c-maxdiscount-wrap').style.display = isPercent ? 'block' : 'none';
    document.getElementById('c-value-label').innerHTML = (isPercent ? 'Value (%)' : 'Value (៛)') + ' <span class="text-danger">*</span>';
}

function openCreate() {
    document.getElementById('couponModalTitle').textContent = 'New Coupon';
    document.getElementById('couponForm').action = cStoreUrl;
    document.getElementById('couponMethod').value = 'POST';
    document.getElementById('couponForm').reset();
    document.getElementById('c-minorder').value = 0;
    document.getElementById('c-peruserlimit').value = 1;
    document.getElementById('c-type').value = 'percent';
    toggleMaxDiscount();
    $('#couponModal').modal('show');
}

function openEdit(btn) {
    const c = JSON.parse(btn.getAttribute('data-coupon'));
    document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
    document.getElementById('couponForm').action = cUpdateBase + c.id;
    document.getElementById('couponMethod').value = 'PUT';
    document.getElementById('c-code').value = c.code;
    document.getElementById('c-description').value = c.description || '';
    document.getElementById('c-type').value = c.type;
    document.getElementById('c-value').value = c.value;
    document.getElementById('c-minorder').value = c.min_order ?? 0;
    document.getElementById('c-maxdiscount').value = c.max_discount ?? '';
    document.getElementById('c-usagelimit').value = c.usage_limit ?? '';
    document.getElementById('c-peruserlimit').value = c.per_user_limit ?? 1;
    document.getElementById('c-servicetype').value = c.service_type;
    document.getElementById('c-startsat').value = c.starts_at || '';
    document.getElementById('c-expiresat').value = c.expires_at || '';
    document.getElementById('c-active').checked = !!c.active;
    toggleMaxDiscount();
    $('#couponModal').modal('show');
}
</script>
@endpush
