@extends('admin.layout')
@section('title', 'Promo Coupons')
@section('page-title', 'Promo Coupons')

@section('content')

<x-admin.page-toolbar subtitle="Codes customers enter at checkout to get a discount on rides, deliveries, moving or shop orders.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Create Coupon</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Total Coupons" :value="number_format($stats['total'])" icon="fa-ticket" tone="primary"
                       :href="route('admin.promo-coupons')" :active="!request('status')" />
    <x-admin.stat-card label="Active" :value="number_format($stats['active'])" icon="fa-circle-check" tone="success" sub="on and not expired"
                       :href="route('admin.promo-coupons', ['status' => 'active'])" :active="request('status') === 'active'" />
    <x-admin.stat-card label="Expired" :value="number_format($stats['expired'])" icon="fa-hourglass-end" tone="muted"
                       :href="route('admin.promo-coupons', ['status' => 'expired'])" :active="request('status') === 'expired'" />
    <x-admin.stat-card label="Total Redemptions" :value="number_format($stats['redemptions'])" icon="fa-receipt" tone="violet" sub="times a code was used" />
</x-admin.stat-grid>

@php $serviceLabels = \App\Models\PromoCode::SERVICE_TYPES; @endphp
<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.promo-coupons')" placeholder="Search code or description" :count="$coupons->total()" noun="coupon">
            <x-admin.filter-select name="status" :options="['active' => 'Active', 'expired' => 'Expired', 'inactive' => 'Turned off']" all="All statuses" />
            <x-admin.filter-select name="service" :options="$serviceLabels" all="All services" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Coupon</th>
                    <th>Discount</th>
                    <th>Service</th>
                    <th>Usage</th>
                    <th>Valid</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($coupons as $c)
                @php
                    $expired = $c->expires_at && $c->expires_at->isPast();
                    [$state, $tone] = ! $c->active ? ['Turned off', 'muted'] : ($expired ? ['Expired', 'muted'] : ['Active', 'success']);
                    $pct = $c->usage_limit ? min(100, round($c->used_count / $c->usage_limit * 100)) : null;
                @endphp
                <tr data-coupon="{{ json_encode([
                        'id'             => $c->id,
                        ...$c->only(['description_en', 'description_kh', 'description_zh']),
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
                    ]) }}">
                    <td>
                        <span class="badge badge-primary" style="font-size:.78rem;letter-spacing:.06em;font-family:ui-monospace,monospace;">{{ $c->code }}</span>
                        @if($c->description)
                            <div class="text-muted text-truncate tw:mt-1 tw:text-xs" style="max-width:240px;" title="{{ $c->description }}">{{ $c->description }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $c->type === 'percent' ? rtrim(rtrim(number_format($c->value, 2), '0'), '.') . '% off' : number_format($c->value) . ' ៛ off' }}</div>
                        <div class="text-muted tw:text-xs">
                            @if($c->type === 'percent' && $c->max_discount) up to {{ number_format($c->max_discount) }} ៛ @endif
                            @if($c->min_order > 0) {{ $c->type === 'percent' && $c->max_discount ? '·' : '' }} min {{ number_format($c->min_order) }} ៛ @endif
                        </div>
                    </td>
                    <td><span class="badge badge-secondary">{{ $serviceLabels[$c->service_type] ?? ucfirst((string) $c->service_type) }}</span></td>
                    <td style="min-width:130px;">
                        <div class="tw:text-xs"><b style="color:var(--ink-900);">{{ number_format($c->used_count) }}</b> / {{ $c->usage_limit ? number_format($c->usage_limit) : '∞' }} used</div>
                        @if($pct !== null)
                            <div class="svc-bar" style="height:5px;border-radius:9px;background:var(--ink-100);margin-top:4px;overflow:hidden;">
                                <span style="display:block;height:100%;width:{{ $pct }}%;background:{{ $pct >= 90 ? 'var(--danger)' : 'var(--primary)' }};"></span>
                            </div>
                        @endif
                        <div class="text-muted tw:text-xs">{{ $c->per_user_limit }} per customer</div>
                    </td>
                    <td>
                        @if($c->starts_at || $c->expires_at)
                            <div>{{ $c->starts_at?->format('d M Y') ?? 'Now' }} → {{ $c->expires_at?->format('d M Y') ?? 'No end' }}</div>
                            @if($c->expires_at && ! $expired)<div class="text-muted tw:text-xs">ends {{ $c->expires_at->diffForHumans() }}</div>@endif
                        @else
                            <span class="text-muted">No limit</span>
                        @endif
                    </td>
                    <td><span class="status-pill tone-{{ $tone }}">{{ $state }}</span></td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(this.closest('tr'))"
                                             :delete="route('admin.promo-coupons.destroy', $c)" :confirm="'Delete coupon ' . $c->code . '? This cannot be undone.'" />
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="7" icon="fa-ticket" title="No coupons yet"
                                   hint="Create a code to give customers a discount." :clear="route('admin.promo-coupons')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$coupons" />
</div>

<div class="modal fade" id="couponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="couponModalTitle">Create Coupon</h5>
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
                            <label>Description <small class="text-muted">(English, optional)</small></label>
                            <input type="text" name="description" id="c-description" class="form-control" maxlength="255"
                                   placeholder="e.g. 50% off for new users">
                        </div>
                    </div>
                    @include('admin.partials.localized-inputs', ['field' => 'description', 'label' => 'Description', 'idPrefix' => 'c-description', 'maxlength' => 255])
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
                                @foreach (\App\Models\PromoCode::SERVICE_TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Starts At <small class="text-muted">(optional, your local time)</small></label>
                            <input type="datetime-local" name="starts_at" id="c-startsat" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Expires At <small class="text-muted">(optional, your local time)</small></label>
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

// The server stores/compares starts_at & expires_at in UTC, but admins think in their
// own local clock — these convert between the two so a "9 AM" typed here really means
// 9 AM local, not 9 AM UTC (that mismatch previously made freshly-created coupons look
// "not started yet" for hours after creation).
function pad(n) { return String(n).padStart(2, '0'); }

function utcStringToLocalInput(utcString) {
    if (!utcString) return '';
    const d = new Date(utcString + 'Z');
    if (isNaN(d.getTime())) return '';
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function localInputToUtcString(localString) {
    if (!localString) return '';
    const d = new Date(localString);
    if (isNaN(d.getTime())) return '';
    return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:00`;
}

document.getElementById('couponForm').addEventListener('submit', function () {
    document.getElementById('c-startsat').value = localInputToUtcString(document.getElementById('c-startsat').value);
    document.getElementById('c-expiresat').value = localInputToUtcString(document.getElementById('c-expiresat').value);
});

function toggleMaxDiscount() {
    const isPercent = document.getElementById('c-type').value === 'percent';
    document.getElementById('c-maxdiscount-wrap').style.display = isPercent ? 'block' : 'none';
    document.getElementById('c-value-label').innerHTML = (isPercent ? 'Value (%)' : 'Value (៛)') + ' <span class="text-danger">*</span>';
}

function openCreate() {
    document.getElementById('couponModalTitle').textContent = 'Create Coupon';
    document.getElementById('couponForm').action = cStoreUrl;
    document.getElementById('couponMethod').value = 'POST';
    document.getElementById('couponForm').reset();
    document.getElementById('c-minorder').value = 0;
    document.getElementById('c-peruserlimit').value = 1;
    document.getElementById('c-type').value = 'percent';
    toggleMaxDiscount();
    $('#couponModal').modal('show');
}

function openEdit(row) {
    const c = JSON.parse(row.getAttribute('data-coupon'));
    document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
    document.getElementById('couponForm').action = cUpdateBase + c.id;
    document.getElementById('couponMethod').value = 'PUT';
    document.getElementById('c-code').value = c.code;
    document.getElementById('c-description').value = c.description_en || c.description || '';
    fillLocalized('c-description', c, 'description');
    document.getElementById('c-type').value = c.type;
    document.getElementById('c-value').value = c.value;
    document.getElementById('c-minorder').value = c.min_order ?? 0;
    document.getElementById('c-maxdiscount').value = c.max_discount ?? '';
    document.getElementById('c-usagelimit').value = c.usage_limit ?? '';
    document.getElementById('c-peruserlimit').value = c.per_user_limit ?? 1;
    document.getElementById('c-servicetype').value = c.service_type;
    document.getElementById('c-startsat').value = utcStringToLocalInput(c.starts_at);
    document.getElementById('c-expiresat').value = utcStringToLocalInput(c.expires_at);
    document.getElementById('c-active').checked = !!c.active;
    toggleMaxDiscount();
    $('#couponModal').modal('show');
}
</script>
@endpush
