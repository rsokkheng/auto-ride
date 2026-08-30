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
                    <input type="number" name="delivery_night_surcharge_rate" id="f-night-rate" class="form-control fare-input"
                           value="{{ $settings['delivery_night_surcharge_rate']->value ?? 0.15 }}"
                           step="0.01" min="0" max="1" required oninput="calcSimulator()">
                    <div class="input-group-append"><span class="input-group-text">× rate</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 0.15 = +15% ដឹកយប់</small>
            </div>
            <div class="col-md-6">
                <div class="field-label">Express Multiplier</div>
                <div class="input-group">
                    <input type="number" name="delivery_express_multiplier" id="f-express-mult" class="form-control fare-input"
                           value="{{ $settings['delivery_express_multiplier']->value ?? 1.25 }}"
                           step="0.01" min="1" max="10" required oninput="calcSimulator()">
                    <div class="input-group-append"><span class="input-group-text">×</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 1.25 = +25% for express delivery</small>
            </div>
        </div>
    </div>
</div>

{{-- ── Company Commission & Driver Split ────────────────────────────────── --}}
<div class="fare-section">
    <div class="fare-sec-hdr" style="background:#fef3c7;">
        <div class="fare-sec-icon" style="background:#d97706;"><i class="fas fa-hand-holding-usd"></i></div>
        <div>
            <div class="font-weight-bold" style="color:#1e293b;">Company Commission &amp; Driver Share</div>
            <small class="text-muted">Percentage split of delivery fee between Company and Driver</small>
        </div>
    </div>
    <div class="fare-sec-body">
        <div class="row">
            <div class="col-md-6">
                <div class="field-label">Company Platform Commission (%)</div>
                <div class="input-group">
                    <input type="number" name="delivery_commission_pct" id="f-comm-pct" class="form-control fare-input"
                           value="{{ $settings['delivery_commission_pct']->value ?? 25 }}"
                           step="0.5" min="0" max="100" required oninput="calcSimulator()">
                    <div class="input-group-append"><span class="input-group-text">% Company Cut</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">ឧទាហរណ៍ 25% = ក្រុមហ៊ុនទទួលបាន 3,000 ៛ ពី 12,000 ៛</small>
            </div>
            <div class="col-md-6">
                <div class="field-label">Driver Net Share (%)</div>
                <div class="input-group">
                    <input type="text" id="disp-driver-pct" class="form-control fare-input bg-light" readonly value="75%">
                    <div class="input-group-append"><span class="input-group-text">% Driver Net</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">ចំណូលសុទ្ធដែលអ្នកដឹករកបានក្នុង ១ជើងពីក្រុមហ៊ុន</small>
            </div>
        </div>
    </div>
</div>

{{-- ── Real-Time Fare & Earnings Simulator ───────────────────────────────── --}}
<div class="card shadow-sm border-0 mb-4" style="border-radius:12px;background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%);color:#fff;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3" style="border-color:rgba(255,255,255,0.15)!important;">
            <div class="d-flex align-items-center">
                <div class="mr-3 p-2 bg-primary rounded-circle text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="fas fa-calculator"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-white font-weight-bold">Live Delivery Fare &amp; Earnings Simulator</h5>
                    <small class="text-white-50">តេស្តមើលតម្លៃ Delivery Fee, ចំណូល Driver Net និង ចំណែក Company ជាក់ស្តែង</small>
                </div>
            </div>
            <span class="badge badge-success px-3 py-2 font-weight-normal" style="font-size:.85rem;">
                <i class="fas fa-bolt mr-1"></i>Real-time Preview
            </span>
        </div>

        <div class="row">
            {{-- Controls --}}
            <div class="col-md-5 border-right" style="border-color:rgba(255,255,255,0.15)!important;">
                <div class="form-group mb-3">
                    <label class="text-white-50 small text-uppercase font-weight-bold">Distance (ចំងាយផ្លូវ)</label>
                    <div class="input-group">
                        <input type="number" id="sim-distance" class="form-control text-dark font-weight-bold" value="5.0" step="0.5" min="0.5" oninput="calcSimulator()">
                        <div class="input-group-append"><span class="input-group-text font-weight-bold">KM</span></div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="text-white-50 small text-uppercase font-weight-bold">Package Size (ប្រភេទទំហំ)</label>
                    <select id="sim-pkg-size" class="form-control text-dark font-weight-bold" onchange="calcSimulator()">
                        <option value="small">Small Package (តូច)</option>
                        <option value="medium" selected>Medium Package (មធ្យម)</option>
                        <option value="large">Large Package (ធំ)</option>
                        <option value="extra_large">Extra Large (ធំពិសេស)</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group col-6 mb-2">
                        <label class="text-white-50 small text-uppercase font-weight-bold">Time (ពេល)</label>
                        <select id="sim-time" class="form-control text-dark font-weight-bold" onchange="calcSimulator()">
                            <option value="day">Daytime (ពេលថ្ងៃ)</option>
                            <option value="night">Night 22:00-05:00 (ពេលយប់)</option>
                        </select>
                    </div>
                    <div class="form-group col-6 mb-2">
                        <label class="text-white-50 small text-uppercase font-weight-bold">Speed (សេវាកម្ម)</label>
                        <select id="sim-option" class="form-control text-dark font-weight-bold" onchange="calcSimulator()">
                            <option value="normal">Normal (ធម្មតា)</option>
                            <option value="express">Express (បន្ទាន់)</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Live Results Cards --}}
            <div class="col-md-7 pl-md-4">
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <div class="p-3 rounded text-center" style="background:rgba(14,165,233,0.18);border:1px solid rgba(14,165,233,0.4);">
                            <small class="text-uppercase text-info font-weight-bold d-block mb-1">Delivery Fee</small>
                            <h4 class="text-white font-weight-bold mb-0" id="sim-disp-total">12,000 ៛</h4>
                            <small class="text-white-50" style="font-size:.7rem;">ថ្លៃសេវាពីអ្នក Booking</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="p-3 rounded text-center" style="background:rgba(16,185,129,0.18);border:1px solid rgba(16,185,129,0.4);">
                            <small class="text-uppercase text-success font-weight-bold d-block mb-1">Driver Net</small>
                            <h4 class="text-success font-weight-bold mb-0" id="sim-disp-driver">9,000 ៛</h4>
                            <small class="text-white-50" style="font-size:.7rem;">អ្នកដឹករកបាន (<span id="sim-disp-driver-pct-sub">75</span>%)</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="p-3 rounded text-center" style="background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.4);">
                            <small class="text-uppercase text-warning font-weight-bold d-block mb-1">Company Share</small>
                            <h4 class="text-warning font-weight-bold mb-0" id="sim-disp-company">3,000 ៛</h4>
                            <small class="text-white-50" style="font-size:.7rem;">សម្រាប់ក្រុមហ៊ុន (<span id="sim-disp-comm-pct-sub">25</span>%)</small>
                        </div>
                    </div>
                </div>

                {{-- Breakdown list --}}
                <div class="p-3 rounded" style="background:rgba(255,255,255,0.06);font-size:.82rem;line-height:1.7;">
                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1" style="border-color:rgba(255,255,255,0.1)!important;">
                        <span class="text-white-50">Base Fee + Booking Fee:</span>
                        <span class="text-white font-weight-bold" id="sim-breakdown-base">3,500 ៛</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1" style="border-color:rgba(255,255,255,0.1)!important;">
                        <span class="text-white-50">Distance Rate (<span id="sim-breakdown-km">5.0</span> km):</span>
                        <span class="text-white font-weight-bold" id="sim-breakdown-dist">6,000 ៛</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1" style="border-color:rgba(255,255,255,0.1)!important;">
                        <span class="text-white-50">Package Size Surcharge:</span>
                        <span class="text-white font-weight-bold" id="sim-breakdown-pkg">2,000 ៛</span>
                    </div>
                    <div class="d-flex justify-content-between" id="sim-row-night">
                        <span class="text-white-50">Night Surcharge:</span>
                        <span class="text-white font-weight-bold" id="sim-breakdown-night">0 ៛</span>
                    </div>
                </div>
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

@push('scripts')
<script>
function calcSimulator() {
    const baseFee = parseFloat(document.querySelector('input[name="delivery_fee_base"]').value) || 3000;
    const perKm = parseFloat(document.querySelector('input[name="delivery_fee_per_km"]').value) || 1200;
    const sSmall = parseFloat(document.querySelector('input[name="delivery_fee_surcharge_small"]').value) || 0;
    const sMedium = parseFloat(document.querySelector('input[name="delivery_fee_surcharge_medium"]').value) || 2000;
    const sLarge = parseFloat(document.querySelector('input[name="delivery_fee_surcharge_large"]').value) || 5000;
    const sXl = parseFloat(document.querySelector('input[name="delivery_fee_surcharge_extra_large"]').value) || 5000;
    const nightRate = parseFloat(document.getElementById('f-night-rate').value) || 0.15;
    const expressMult = parseFloat(document.getElementById('f-express-mult').value) || 1.25;
    const commPct = parseFloat(document.getElementById('f-comm-pct').value) || 25;

    // Update driver % display
    const driverPct = Math.max(0, 100 - commPct);
    document.getElementById('disp-driver-pct').value = driverPct + '%';
    document.getElementById('sim-disp-comm-pct-sub').textContent = commPct;
    document.getElementById('sim-disp-driver-pct-sub').textContent = driverPct;

    // Simulator inputs
    const distanceKm = Math.max(0.5, parseFloat(document.getElementById('sim-distance').value) || 1);
    const pkgSize = document.getElementById('sim-pkg-size').value;
    const isNight = document.getElementById('sim-time').value === 'night';
    const isExpress = document.getElementById('sim-option').value === 'express';

    let pkgSurcharge = 0;
    if (pkgSize === 'medium') pkgSurcharge = sMedium;
    else if (pkgSize === 'large') pkgSurcharge = sLarge;
    else if (pkgSize === 'extra_large') pkgSurcharge = sXl;
    else pkgSurcharge = sSmall;

    const bookingFee = 500;
    const distanceFare = Math.ceil(distanceKm * perKm);
    let subtotal = bookingFee + baseFee + distanceFare + pkgSurcharge;

    let nightSurcharge = 0;
    if (isNight) {
        nightSurcharge = Math.ceil(subtotal * nightRate);
    }
    let total = subtotal + nightSurcharge;

    if (isExpress) {
        total = Math.ceil(total * expressMult);
    }

    // Round to nearest 100
    total = Math.ceil(total / 100) * 100;

    const companyShare = Math.floor(total * (commPct / 100));
    const driverNet = Math.max(0, total - companyShare);

    // Render results
    document.getElementById('sim-disp-total').textContent = total.toLocaleString() + ' ៛';
    document.getElementById('sim-disp-driver').textContent = driverNet.toLocaleString() + ' ៛';
    document.getElementById('sim-disp-company').textContent = companyShare.toLocaleString() + ' ៛';

    document.getElementById('sim-breakdown-base').textContent = (baseFee + bookingFee).toLocaleString() + ' ៛';
    document.getElementById('sim-breakdown-km').textContent = distanceKm.toFixed(1);
    document.getElementById('sim-breakdown-dist').textContent = distanceFare.toLocaleString() + ' ៛';
    document.getElementById('sim-breakdown-pkg').textContent = pkgSurcharge.toLocaleString() + ' ៛';
    document.getElementById('sim-breakdown-night').textContent = (nightSurcharge > 0 ? '+ ' + nightSurcharge.toLocaleString() + ' ៛' : '0 ៛ (គ្មាន)');
}

document.addEventListener('DOMContentLoaded', calcSimulator);
</script>
@endpush

@endsection
