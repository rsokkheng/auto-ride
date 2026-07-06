@extends('partner.layout')
@section('title', 'New Order')
@section('page-title', 'Create Delivery Order')

@section('content')

<div class="max-w-3xl mx-auto">

    {{-- Header card --}}
    <div class="rounded-2xl p-5 mb-5 text-white" style="background:linear-gradient(135deg,#e63946,#c1121f)">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-plus-circle text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold leading-tight">New Delivery Order</h2>
                <p class="text-red-100 text-sm mt-0.5">Flat-rate fee calculated from your contract</p>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('partner.orders.store') }}" id="order-form">
        @csrf

        {{-- ── Recipient ────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-4">
            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">
                <i class="fas fa-user me-1"></i> Recipient Info
            </h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Recipient Name <span class="text-danger">*</span></label>
                    <input type="text" name="recipient_name"
                           class="form-control @error('recipient_name') is-invalid @enderror"
                           value="{{ old('recipient_name') }}" placeholder="Full name" required>
                    @error('recipient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Recipient Phone <span class="text-danger">*</span></label>
                    <input type="text" name="recipient_phone"
                           class="form-control @error('recipient_phone') is-invalid @enderror"
                           value="{{ old('recipient_phone') }}" placeholder="012 345 678" required>
                    @error('recipient_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ── Pickup & Dropoff ─────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-4">
            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">
                <i class="fas fa-map-marker-alt me-1"></i> Pickup &amp; Dropoff
            </h3>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Pickup Address <span class="text-danger">*</span></label>
                <input type="text" name="pickup_address" id="pickup_address"
                       class="form-control @error('pickup_address') is-invalid @enderror"
                       value="{{ old('pickup_address') }}" placeholder="Enter full pickup address" required>
                @error('pickup_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <label class="form-label small text-muted">Pickup Latitude</label>
                    <input type="number" name="pickup_lat" id="pickup_lat" class="form-control form-control-sm" step="any"
                           value="{{ old('pickup_lat') }}" placeholder="11.5564">
                </div>
                <div class="col-6">
                    <label class="form-label small text-muted">Pickup Longitude</label>
                    <input type="number" name="pickup_lng" id="pickup_lng" class="form-control form-control-sm" step="any"
                           value="{{ old('pickup_lng') }}" placeholder="104.9282">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Dropoff Address <span class="text-danger">*</span></label>
                <input type="text" name="dropoff_address" id="dropoff_address"
                       class="form-control @error('dropoff_address') is-invalid @enderror"
                       value="{{ old('dropoff_address') }}" placeholder="Enter full delivery address" required>
                @error('dropoff_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label small text-muted">Dropoff Latitude</label>
                    <input type="number" name="dropoff_lat" id="dropoff_lat" class="form-control form-control-sm" step="any"
                           value="{{ old('dropoff_lat') }}" placeholder="11.5700">
                </div>
                <div class="col-6">
                    <label class="form-label small text-muted">Dropoff Longitude</label>
                    <input type="number" name="dropoff_lng" id="dropoff_lng" class="form-control form-control-sm" step="any"
                           value="{{ old('dropoff_lng') }}" placeholder="104.9100">
                </div>
            </div>
        </div>

        {{-- ── Package & Payment ────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-4">
            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">
                <i class="fas fa-box me-1"></i> Package &amp; Payment
            </h3>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Package Size</label>
                    <select name="package_size" id="package_size" class="form-select" onchange="updateFeePreview()">
                        <option value="small"       {{ old('package_size','small') == 'small'       ? 'selected' : '' }}>Small</option>
                        <option value="medium"      {{ old('package_size')         == 'medium'      ? 'selected' : '' }}>Medium</option>
                        <option value="large"       {{ old('package_size')         == 'large'       ? 'selected' : '' }}>Large</option>
                        <option value="extra_large" {{ old('package_size')         == 'extra_large' ? 'selected' : '' }}>Extra Large</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Delivery Speed</label>
                    <select name="service_option" id="service_option" class="form-select" onchange="updateFeePreview()">
                        <option value="normal"  {{ old('service_option','normal') == 'normal'  ? 'selected' : '' }}>Normal</option>
                        <option value="express" {{ old('service_option')          == 'express' ? 'selected' : '' }}>Express</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Fee Paid By</label>
                    <select name="payment_by" class="form-select">
                        <option value="recipient" {{ old('payment_by','recipient') == 'recipient' ? 'selected' : '' }}>Recipient (COD)</option>
                        <option value="sender"    {{ old('payment_by')             == 'sender'    ? 'selected' : '' }}>Sender (Prepaid)</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Package Value (KHR)</label>
                    <div class="input-group">
                        <input type="number" name="package_amount" class="form-control"
                               value="{{ old('package_amount', 0) }}" min="0" step="1000" placeholder="0">
                        <span class="input-group-text">៛</span>
                    </div>
                    <div class="form-text">Value of goods (for COD reference only)</div>
                </div>
            </div>

            {{-- Fee Preview Box --}}
            <div class="rounded-xl p-4 flex items-center justify-between" style="background:#f0fdf4;border:1.5px solid #bbf7d0">
                <div>
                    <div class="text-xs text-slate-500 font-medium mb-0.5">Delivery Fee (from your contract)</div>
                    <div class="text-2xl font-extrabold text-emerald-600" id="fee-display">—</div>
                    <div class="text-xs text-slate-400" id="fee-breakdown"></div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-calculator text-emerald-500 text-lg"></i>
                </div>
            </div>
        </div>

        {{-- ── Notes ───────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-5">
            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">
                <i class="fas fa-sticky-note me-1"></i> Additional Info
            </h3>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Partner Reference / Order No.</label>
                <input type="text" name="partner_reference" class="form-control"
                       value="{{ old('partner_reference') }}" placeholder="Your internal order number (optional)">
            </div>
            <div>
                <label class="form-label fw-semibold small">Notes for Driver</label>
                <textarea name="notes" class="form-control" rows="2"
                          placeholder="Special instructions (optional)">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- ── Actions ──────────────────────────────────────────── --}}
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('partner.orders') }}"
               class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <button type="submit"
                    class="btn text-white px-6"
                    style="background:linear-gradient(135deg,#e63946,#c1121f);border:none">
                <i class="fas fa-qrcode me-2"></i>Create Order &amp; Generate QR
            </button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
var CONTRACT = {
    normal_fee:            {{ $contract->normal_fee            ?? 5000 }},
    express_fee:           {{ $contract->express_fee           ?? 10000 }},
    surcharge_large:       {{ $contract->surcharge_large       ?? 5000 }},
    surcharge_extra_large: {{ $contract->surcharge_extra_large ?? 5000 }},
};

function fmt(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

function updateFeePreview() {
    var size   = document.getElementById('package_size').value;
    var option = document.getElementById('service_option').value;
    var base   = option === 'express' ? CONTRACT.express_fee : CONTRACT.normal_fee;
    var surcharge = 0;
    if (size === 'large')       surcharge = CONTRACT.surcharge_large;
    if (size === 'extra_large') surcharge = CONTRACT.surcharge_extra_large;
    var total = base + surcharge;

    document.getElementById('fee-display').textContent = fmt(total) + ' ៛';
    var parts = [(option === 'express' ? 'Express' : 'Normal') + ' ' + fmt(base) + ' ៛'];
    if (surcharge > 0) parts.push('+' + fmt(surcharge) + ' ៛ size surcharge');
    document.getElementById('fee-breakdown').textContent = parts.join(' ');
}

document.addEventListener('DOMContentLoaded', updateFeePreview);
</script>
@endpush
