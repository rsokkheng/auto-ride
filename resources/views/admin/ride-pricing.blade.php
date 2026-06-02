@extends('admin.layout')
@section('title', 'Ride Pricing')
@section('page-title', 'Ride Fare Pricing')

@push('styles')
<style>
    .pricing-card          { border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:20px; }
    .pricing-card-header   { padding:14px 20px; display:flex; align-items:center; gap:12px; }
    .pricing-card-body     { padding:20px; background:#fff; }
    .pricing-icon          { width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff; }
    .field-label           { font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:4px; }
    .fare-input            { border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:.9rem;width:100%;outline:none;transition:border .2s; }
    .fare-input:focus      { border-color:#e63946;box-shadow:0 0 0 3px rgba(230,57,70,.1); }
    .fare-input.currency   { padding-left:38px; }
    .input-wrap            { position:relative; }
    .input-prefix          { position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.8rem;color:#94a3b8;pointer-events:none; }
    .formula-box           { background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#64748b;margin-top:12px; }
    .live-preview          { font-size:.8rem;color:#e63946;font-weight:600;margin-top:4px; }
</style>
@endpush

@section('content')

{{-- Page intro --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-0" style="font-size:.875rem;">
            All amounts in <strong>KHR ៛</strong>. Changes take effect immediately after saving (cache cleared automatically).
        </p>
    </div>
</div>

{{-- ── Service Type Cards ─────────────────────────────────────────────── --}}
@php
$colors = [
    'motorcycle' => '#f97316',
    'tuk_tuk'    => '#8b5cf6',
    'standard'   => '#3b82f6',
    'premium'    => '#eab308',
    'shared'     => '#10b981',
    'van'        => '#ef4444',
];
@endphp

<div class="row">
@foreach($pricing as $p)
@php $color = $colors[$p->service_type] ?? '#64748b'; @endphp
<div class="col-md-6 col-lg-4">
    <div class="pricing-card">
        <div class="pricing-card-header" style="background:{{ $color }}18;border-bottom:1px solid {{ $color }}30;">
            <div class="pricing-icon" style="background:{{ $color }};">
                <i class="fas {{ $p->icon }}"></i>
            </div>
            <div class="flex-1">
                <div class="font-weight-bold" style="color:#1e293b;">{{ $p->label }}</div>
                <small class="text-muted">{{ ucfirst(str_replace('_',' ',$p->service_type)) }} · Max {{ $p->capacity }} pax</small>
            </div>
            <div>
                <span class="badge badge-{{ $p->active ? 'success' : 'secondary' }}">
                    {{ $p->active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>

        <div class="pricing-card-body">
            <form method="POST" action="{{ route('admin.ride-pricing.update', $p) }}">
                @csrf @method('PUT')

                <div class="row">
                    {{-- Base fare --}}
                    <div class="col-6 mb-3">
                        <div class="field-label">Base Fare</div>
                        <div class="input-wrap">
                            <span class="input-prefix">៛</span>
                            <input type="number" name="base" class="fare-input currency" value="{{ $p->base }}" min="0" step="100" required
                                   oninput="updatePreview('{{ $p->service_type }}')">
                        </div>
                    </div>
                    {{-- Booking fee --}}
                    <div class="col-6 mb-3">
                        <div class="field-label">Booking Fee</div>
                        <div class="input-wrap">
                            <span class="input-prefix">៛</span>
                            <input type="number" name="booking_fee" class="fare-input currency" value="{{ $p->booking_fee }}" min="0" step="100" required
                                   oninput="updatePreview('{{ $p->service_type }}')">
                        </div>
                    </div>
                    {{-- Per km --}}
                    <div class="col-6 mb-3">
                        <div class="field-label">Per KM</div>
                        <div class="input-wrap">
                            <span class="input-prefix">៛</span>
                            <input type="number" name="per_km" class="fare-input currency" value="{{ $p->per_km }}" min="0" step="50" required
                                   oninput="updatePreview('{{ $p->service_type }}')">
                        </div>
                    </div>
                    {{-- Per minute (traffic) --}}
                    <div class="col-6 mb-3">
                        <div class="field-label">Per Min <small class="text-muted">(traffic)</small></div>
                        <div class="input-wrap">
                            <span class="input-prefix">៛</span>
                            <input type="number" name="per_min" class="fare-input currency" value="{{ $p->per_min }}" min="0" step="50" required>
                        </div>
                    </div>
                    {{-- Minimum fare --}}
                    <div class="col-6 mb-3">
                        <div class="field-label">Minimum Fare</div>
                        <div class="input-wrap">
                            <span class="input-prefix">៛</span>
                            <input type="number" name="minimum" class="fare-input currency" value="{{ $p->minimum }}" min="0" step="100" required>
                        </div>
                    </div>
                    {{-- Capacity --}}
                    <div class="col-6 mb-3">
                        <div class="field-label">Capacity</div>
                        <input type="number" name="capacity" class="fare-input" value="{{ $p->capacity }}" min="1" max="20" required>
                    </div>
                </div>

                {{-- Hidden fields (label, icon stay the same unless editing) --}}
                <input type="hidden" name="label" value="{{ $p->label }}">
                <input type="hidden" name="icon"  value="{{ $p->icon }}">

                {{-- Active toggle --}}
                <div class="custom-control custom-switch mb-3">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" class="custom-control-input" name="active"
                           id="active_{{ $p->service_type }}" value="1" {{ $p->active ? 'checked' : '' }}>
                    <label class="custom-control-label" for="active_{{ $p->service_type }}">
                        Service is Active
                    </label>
                </div>

                {{-- Live fare preview (5 km trip) --}}
                <div class="formula-box" id="preview_{{ $p->service_type }}">
                    <i class="fas fa-calculator mr-1"></i>
                    <strong>5 km estimate:</strong>
                    <span class="live-preview" id="preview_val_{{ $p->service_type }}">
                        {{ number_format($p->booking_fee + $p->base + ($p->per_km * 5), 0) }} ៛
                    </span>
                    <br>
                    <span class="text-muted" style="font-size:.72rem;">
                        booking_fee + base + (per_km × 5km)
                    </span>
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-3" style="border-radius:8px;">
                    <i class="fas fa-save mr-1"></i> Save {{ $p->label }}
                </button>
            </form>
        </div>
    </div>
</div>
@endforeach
</div>

{{-- ── Global Settings ─────────────────────────────────────────────────── --}}
<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="fas fa-sliders-h mr-2 text-primary"></i> Global Pricing Settings
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.ride-pricing.settings') }}">
            @csrf
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="field-label">Night Surcharge (Rides)</label>
                    <div class="input-group">
                        <input type="number" name="night_surcharge_rate" class="form-control"
                               value="{{ $settings['night_surcharge_rate']->value ?? 0.20 }}"
                               step="0.01" min="0" max="1" required>
                        <div class="input-group-append"><span class="input-group-text">× rate</span></div>
                    </div>
                    <small class="text-muted">e.g. 0.20 = +20% (22:00–05:00)</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="field-label">Night Surcharge (Delivery)</label>
                    <div class="input-group">
                        <input type="number" name="delivery_night_surcharge_rate" class="form-control"
                               value="{{ $settings['delivery_night_surcharge_rate']->value ?? 0.15 }}"
                               step="0.01" min="0" max="1" required>
                        <div class="input-group-append"><span class="input-group-text">× rate</span></div>
                    </div>
                    <small class="text-muted">e.g. 0.15 = +15%</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="field-label">Avg City Speed (km/h)</label>
                    <div class="input-group">
                        <input type="number" name="avg_city_speed_kmh" class="form-control"
                               value="{{ $settings['avg_city_speed_kmh']->value ?? 30 }}"
                               min="5" max="120" required>
                        <div class="input-group-append"><span class="input-group-text">km/h</span></div>
                    </div>
                    <small class="text-muted">Used when Google Maps unavailable</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="field-label">Traffic Threshold (km/h)</label>
                    <div class="input-group">
                        <input type="number" name="traffic_speed_threshold_kmh" class="form-control"
                               value="{{ $settings['traffic_speed_threshold_kmh']->value ?? 20 }}"
                               min="5" max="60" required>
                        <div class="input-group-append"><span class="input-group-text">km/h</span></div>
                    </div>
                    <small class="text-muted">Per-min rate activates below this speed</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Save Global Settings
            </button>
        </form>
    </div>
</div>

{{-- ── Fare Formula Reference ──────────────────────────────────────────── --}}
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="fas fa-info-circle mr-2 text-info"></i> Fare Formula Reference
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="font-weight-bold">Rides</h6>
                <code style="font-size:.82rem;background:#f8fafc;padding:10px;border-radius:8px;display:block;line-height:1.8;">
                    fare = booking_fee<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; + base_fare<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; + (per_km × distance_km)<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; + (per_min × minutes)  <span style="color:#94a3b8;">← if speed &lt; threshold</span><br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; + night_surcharge  <span style="color:#94a3b8;">← 22:00–05:00</span><br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; × surge_multiplier<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; rounded up to 100 ៛<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; max(result, minimum_fare)
                </code>
            </div>
            <div class="col-md-6">
                <h6 class="font-weight-bold">Example — Standard, 5 km, no surge, daytime</h6>
                <table class="table table-sm table-bordered" style="font-size:.8rem;">
                    <tr><td>Booking fee</td><td class="text-right">1,000 ៛</td></tr>
                    <tr><td>Base fare</td><td class="text-right">5,000 ៛</td></tr>
                    <tr><td>Distance (5 km × 1,500)</td><td class="text-right">7,500 ៛</td></tr>
                    <tr><td>Traffic surcharge</td><td class="text-right">0 ៛</td></tr>
                    <tr><td>Night surcharge</td><td class="text-right">0 ៛</td></tr>
                    <tr><td>Surge × 1.0</td><td class="text-right">0 ៛</td></tr>
                    <tr class="table-active font-weight-bold"><td>Total</td><td class="text-right">13,500 ៛</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function updatePreview(type) {
    var form   = document.querySelector('form[action*="' + type + '"], form[action*="ride-pricing"]');
    var cards  = document.querySelectorAll('.pricing-card');
    var target = null;

    cards.forEach(function(card) {
        var btn = card.querySelector('button[type=submit]');
        if (btn && btn.textContent.toLowerCase().indexOf(type.replace('_', '')) !== -1) target = card;
    });

    if (!target) return;

    var base    = parseInt(target.querySelector('[name=base]').value)        || 0;
    var booking = parseInt(target.querySelector('[name=booking_fee]').value) || 0;
    var perKm   = parseInt(target.querySelector('[name=per_km]').value)      || 0;
    var est5km  = booking + base + (perKm * 5);

    var el = document.getElementById('preview_val_' + type);
    if (el) el.textContent = est5km.toLocaleString() + ' ៛';
}
</script>
@endpush
