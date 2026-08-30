@extends('admin.layout')
@section('title', 'Delivery Fare')
@section('page-title', 'Package Delivery Pricing')

@push('styles')
<style>
    .fare-section   { border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:24px; }
    .fare-sec-hdr   { padding:14px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #e2e8f0; }
    .fare-sec-icon  { width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff; }
    .fare-sec-body  { padding:20px;background:#fff; }
    .field-label    { font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:4px; }
    .fare-input     { border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:.9rem;width:100%;outline:none;transition:border .2s; }
    .fare-input:focus { border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,.12); }
    .fare-input.currency { padding-left:38px; }
    .input-wrap     { position:relative; }
    .input-prefix   { position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.8rem;color:#94a3b8;pointer-events:none; }
    .formula-box    { background:#f0f9ff;border:1px dashed #bae6fd;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#075985;margin-top:16px; }
</style>
@endpush

@section('content')

{{-- Flash --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<div class="d-flex align-items-center justify-content-between mb-4">
    <p class="text-muted mb-0" style="font-size:.875rem;">
        All amounts in <strong>KHR ៛</strong>. Changes take effect within 10 minutes (cached).
    </p>
</div>

<form method="POST" action="{{ route('admin.delivery-fare.update') }}">
@csrf

{{-- ── Base & Distance ─────────────────────────────────────────────────── --}}
<div class="fare-section">
    <div class="fare-sec-hdr" style="background:#f0f9ff;">
        <div class="fare-sec-icon" style="background:#0ea5e9;"><i class="fas fa-box"></i></div>
        <div>
            <div class="font-weight-bold" style="color:#1e293b;">Base &amp; Distance Fees</div>
            <small class="text-muted">Fixed charges applied to every package delivery</small>
        </div>
    </div>
    <div class="fare-sec-body">
        <div class="row">
            <div class="col-md-6">
                <div class="field-label">Base Fee</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="delivery_fee_base" class="fare-input currency"
                           value="{{ $settings['delivery_fee_base']->value ?? 3000 }}" min="0" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Flat fee per delivery</small>
            </div>
            <div class="col-md-6">
                <div class="field-label">Per-KM Rate</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="delivery_fee_per_km" class="fare-input currency"
                           value="{{ $settings['delivery_fee_per_km']->value ?? 1200 }}" min="0" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">KHR per km</small>
            </div>
        </div>
        <div class="formula-box mt-3">
            <strong>Formula:</strong> Total = Base Fee + (Per-KM × distance) + Package Surcharge + Night Surcharge (if applicable) × Express Multiplier (if requested)
        </div>
    </div>
</div>

{{-- ── Package Size Surcharge ───────────────────────────────────────────── --}}
<div class="fare-section">
    <div class="fare-sec-hdr" style="background:#f0fdf4;">
        <div class="fare-sec-icon" style="background:#10b981;"><i class="fas fa-boxes-stacked"></i></div>
        <div>
            <div class="font-weight-bold" style="color:#1e293b;">Package Size Surcharge</div>
            <small class="text-muted">Extra KHR added on top of the base + distance fare, by package size</small>
        </div>
    </div>
    <div class="fare-sec-body">
        <div class="row">
            <div class="col-md-3">
                <div class="field-label">Small</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="delivery_fee_surcharge_small" class="fare-input currency"
                           value="{{ $settings['delivery_fee_surcharge_small']->value ?? 0 }}" min="0" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-label">Medium</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="delivery_fee_surcharge_medium" class="fare-input currency"
                           value="{{ $settings['delivery_fee_surcharge_medium']->value ?? 2000 }}" min="0" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-label">Large</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="delivery_fee_surcharge_large" class="fare-input currency"
                           value="{{ $settings['delivery_fee_surcharge_large']->value ?? 5000 }}" min="0" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-label">Extra Large</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="delivery_fee_surcharge_extra_large" class="fare-input currency"
                           value="{{ $settings['delivery_fee_surcharge_extra_large']->value ?? 5000 }}" min="0" required>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Night & Express ──────────────────────────────────────────────────── --}}
<div class="fare-section">
    <div class="fare-sec-hdr" style="background:#fdf4ff;">
        <div class="fare-sec-icon" style="background:#a855f7;"><i class="fas fa-bolt"></i></div>
        <div>
            <div class="font-weight-bold" style="color:#1e293b;">Night &amp; Express Multipliers</div>
            <small class="text-muted">Applied as a percentage/multiplier on top of the subtotal</small>
        </div>
    </div>
    <div class="fare-sec-body">
        <div class="row">
            <div class="col-md-6">
                <div class="field-label">Night Surcharge Rate <small class="text-muted text-lowercase font-weight-normal">(22:00–05:00)</small></div>
                <div class="input-group">
                    <input type="number" name="delivery_night_surcharge_rate" class="form-control fare-input"
                           value="{{ $settings['delivery_night_surcharge_rate']->value ?? 0.15 }}"
                           step="0.01" min="0" max="1" required>
                    <div class="input-group-append"><span class="input-group-text">× rate</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 0.15 = +15%</small>
            </div>
            <div class="col-md-6">
                <div class="field-label">Express Multiplier</div>
                <div class="input-group">
                    <input type="number" name="delivery_express_multiplier" class="form-control fare-input"
                           value="{{ $settings['delivery_express_multiplier']->value ?? 1.25 }}"
                           step="0.01" min="1" max="10" required>
                    <div class="input-group-append"><span class="input-group-text">×</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 1.25 = +25% for express delivery</small>
            </div>
        </div>
    </div>
</div>

{{-- ── Save ─────────────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-end mb-5">
    <button type="submit" class="btn btn-primary px-5" style="font-weight:600;border-radius:8px;">
        <i class="fas fa-save mr-2"></i>Save Delivery Fare Rates
    </button>
</div>

</form>

@endsection
