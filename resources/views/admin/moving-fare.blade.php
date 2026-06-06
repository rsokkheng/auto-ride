@extends('admin.layout')
@section('title', 'Moving Fare')
@section('page-title', 'Moving Service Pricing')

@push('styles')
<style>
    .fare-section   { border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:24px; }
    .fare-sec-hdr   { padding:14px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #e2e8f0; }
    .fare-sec-icon  { width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff; }
    .fare-sec-body  { padding:20px;background:#fff; }
    .field-label    { font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:4px; }
    .fare-input     { border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:.9rem;width:100%;outline:none;transition:border .2s; }
    .fare-input:focus { border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.12); }
    .fare-input.currency { padding-left:38px; }
    .input-wrap     { position:relative; }
    .input-prefix   { position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.8rem;color:#94a3b8;pointer-events:none; }
    .formula-box    { background:#fffbeb;border:1px dashed #fde68a;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#92400e;margin-top:16px; }
    .tier-row       { display:grid;grid-template-columns:140px 1fr;align-items:center;gap:12px;margin-bottom:12px; }
    .tier-label     { font-size:.82rem;font-weight:600;color:#374151; }
    .tier-sub       { font-size:.72rem;color:#94a3b8;font-weight:400; }
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
        All amounts in <strong>KHR ៛</strong>. Changes take effect immediately — no cache to clear.
    </p>
</div>

<form method="POST" action="{{ route('admin.moving-fare.update') }}">
@csrf

{{-- ── Base & Distance ─────────────────────────────────────────────────── --}}
<div class="fare-section">
    <div class="fare-sec-hdr" style="background:#fffbeb;">
        <div class="fare-sec-icon" style="background:#f59e0b;"><i class="fas fa-truck-moving"></i></div>
        <div>
            <div class="font-weight-bold" style="color:#1e293b;">Base &amp; Distance Fees</div>
            <small class="text-muted">Fixed charges applied to every moving job</small>
        </div>
    </div>
    <div class="fare-sec-body">
        <div class="row">
            <div class="col-md-4">
                <div class="field-label">Base Fee</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="moving_base_fee" class="fare-input currency"
                           value="{{ $settings['moving_base_fee']->value ?? 20000 }}" min="0" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Flat fee per job</small>
            </div>
            <div class="col-md-4">
                <div class="field-label">Truck Fee</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="moving_truck_fee" class="fare-input currency"
                           value="{{ $settings['moving_truck_fee']->value ?? 20000 }}" min="0" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Flat truck surcharge</small>
            </div>
            <div class="col-md-4">
                <div class="field-label">Distance Rate</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="moving_distance_rate" class="fare-input currency"
                           value="{{ $settings['moving_distance_rate']->value ?? 4000 }}" min="0" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">KHR per km</small>
            </div>
        </div>
        <div class="formula-box mt-3">
            <strong>Formula:</strong> Total = Base Fee + Truck Fee + (Distance Rate × km) + Helper Fee + Floor Fee
        </div>
    </div>
</div>

{{-- ── Helper Rates ────────────────────────────────────────────────────── --}}
<div class="fare-section">
    <div class="fare-sec-hdr" style="background:#f0fdf4;">
        <div class="fare-sec-icon" style="background:#10b981;"><i class="fas fa-people-carry"></i></div>
        <div>
            <div class="font-weight-bold" style="color:#1e293b;">Helper Rates</div>
            <small class="text-muted">KHR per helper per job (multiplied by number of helpers requested)</small>
        </div>
    </div>
    <div class="fare-sec-body">
        <div class="row">
            <div class="col-md-6">
                <div class="field-label">Normal Carry <small class="text-muted text-lowercase font-weight-normal">(boxes, bags, furniture)</small></div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="moving_helper_rate_normal" class="fare-input currency"
                           value="{{ $settings['moving_helper_rate_normal']->value ?? 8000 }}" min="0" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-label">Heavy Carry <small class="text-muted text-lowercase font-weight-normal">(fridge, sofa, bed, piano)</small></div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="moving_helper_rate_heavy" class="fare-input currency"
                           value="{{ $settings['moving_helper_rate_heavy']->value ?? 16000 }}" min="0" required>
                </div>
            </div>
        </div>
        <div class="formula-box">
            <strong>Example:</strong> 2 heavy helpers = 2 × Heavy Rate KHR
        </div>
    </div>
</div>

{{-- ── Floor Carry Fees ─────────────────────────────────────────────────── --}}
<div class="fare-section">
    <div class="fare-sec-hdr" style="background:#eff6ff;">
        <div class="fare-sec-icon" style="background:#3b82f6;"><i class="fas fa-building"></i></div>
        <div>
            <div class="font-weight-bold" style="color:#1e293b;">Floor Carry Fee Tiers</div>
            <small class="text-muted">Applied to the higher floor of pickup / dropoff. No-elevator penalty applied on top.</small>
        </div>
    </div>
    <div class="fare-sec-body">

        <div class="tier-row">
            <div><div class="tier-label">Ground / F1 <div class="tier-sub">Floor ≤ 1</div></div></div>
            <div class="input-wrap">
                <span class="input-prefix">៛</span>
                <input type="number" name="moving_floor_fee_tier_1" class="fare-input currency"
                       value="{{ $settings['moving_floor_fee_tier_1']->value ?? 4000 }}" min="0" required>
            </div>
        </div>

        <div class="tier-row">
            <div><div class="tier-label">F2 – F3 <div class="tier-sub">Floor 2–3</div></div></div>
            <div class="input-wrap">
                <span class="input-prefix">៛</span>
                <input type="number" name="moving_floor_fee_tier_3" class="fare-input currency"
                       value="{{ $settings['moving_floor_fee_tier_3']->value ?? 12000 }}" min="0" required>
            </div>
        </div>

        <div class="tier-row">
            <div><div class="tier-label">F4 – F6 <div class="tier-sub">Floor 4–6</div></div></div>
            <div class="input-wrap">
                <span class="input-prefix">៛</span>
                <input type="number" name="moving_floor_fee_tier_6" class="fare-input currency"
                       value="{{ $settings['moving_floor_fee_tier_6']->value ?? 20000 }}" min="0" required>
            </div>
        </div>

        <div class="tier-row">
            <div><div class="tier-label">F7 + <div class="tier-sub">Floor 7 and above</div></div></div>
            <div class="input-wrap">
                <span class="input-prefix">៛</span>
                <input type="number" name="moving_floor_fee_tier_7plus" class="fare-input currency"
                       value="{{ $settings['moving_floor_fee_tier_7plus']->value ?? 40000 }}" min="0" required>
            </div>
        </div>

        {{-- No-elevator multiplier --}}
        <div class="mt-3 pt-3 border-top">
            <div class="field-label">No-Elevator Multiplier</div>
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="input-wrap">
                        <span class="input-prefix">×</span>
                        <input type="number" name="moving_no_elevator_mult" class="fare-input"
                               style="padding-left:32px;"
                               value="{{ $settings['moving_no_elevator_mult']->value ?? 1.5 }}"
                               min="1" max="5" step="0.1" required>
                    </div>
                </div>
                <div class="col-md-9">
                    <small class="text-muted">
                        When the building has no elevator, the floor fee is multiplied by this value.
                        E.g. <strong>1.5</strong> means stairs jobs cost 50% more for floor carry.
                    </small>
                </div>
            </div>
        </div>

        <div class="formula-box">
            <strong>Logic:</strong> effective_floor_fee = tier_fee × (no_elevator ? multiplier : 1)
            &nbsp;·&nbsp; tier is chosen from the <em>higher</em> of pickup floor vs dropoff floor
        </div>

    </div>
</div>

{{-- ── Save ─────────────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-end mb-5">
    <button type="submit" class="btn btn-warning px-5" style="font-weight:600;border-radius:8px;">
        <i class="fas fa-save mr-2"></i>Save Moving Fare Rates
    </button>
</div>

</form>

@endsection
