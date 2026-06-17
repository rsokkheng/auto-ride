@extends('admin.layout')
@section('title', 'Fare Management')
@section('page-title', 'Fare & Fee Management')

@push('styles')
<style>
.fm-card        { border-radius:12px;border:1px solid #e2e8f0;background:#fff;margin-bottom:20px;overflow:hidden; }
.fm-header      { padding:14px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #f1f5f9; }
.fm-icon        { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff;flex-shrink:0; }
.fm-title       { font-size:.85rem;font-weight:700;color:#1e293b; }
.fm-subtitle    { font-size:.72rem;color:#94a3b8; }
.fm-body        { padding:20px; }
.field-label    { font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#64748b;margin-bottom:4px; }
.fm-input       { border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:.88rem;width:100%;outline:none;transition:border .15s; }
.fm-input:focus { border-color:#1a73e8;box-shadow:0 0 0 3px rgba(26,115,232,.08); }
.input-wrap     { position:relative; }
.input-prefix   { position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.78rem;color:#94a3b8;pointer-events:none; }
.fm-input.px    { padding-left:36px; }
.tier-badge     { display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:700; }
.info-box       { background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;font-size:.78rem;color:#0369a1; }
</style>
@endpush

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<form method="POST" action="{{ route('admin.fare-management.update') }}">
@csrf

{{-- ── Section 1: Cancellation Fees ───────────────────────────────────── --}}
<div class="fm-card">
    <div class="fm-header">
        <div class="fm-icon" style="background:#ef4444;"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="fm-title">Cancellation Fees</div>
            <div class="fm-subtitle">Charged to the passenger when they cancel after driver actions</div>
        </div>
    </div>
    <div class="fm-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="field-label">Fee After Driver Arrived</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="cancel_fee_after_arrival" class="fm-input px"
                           value="{{ $settings['cancel_fee_after_arrival']->value ?? 3000 }}" min="0" step="500" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Charged when passenger cancels after driver arrived at pickup</small>
            </div>
            <div class="col-md-4 mb-3">
                <div class="field-label">Fee After Accepted (outside free window)</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="cancel_fee_after_accepted" class="fm-input px"
                           value="{{ $settings['cancel_fee_after_accepted']->value ?? 1000 }}" min="0" step="500" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Charged when passenger cancels after free window expires</small>
            </div>
            <div class="col-md-4 mb-3">
                <div class="field-label">Free Cancellation Window</div>
                <div class="input-wrap">
                    <input type="number" name="cancel_free_minutes" class="fm-input"
                           value="{{ $settings['cancel_free_minutes']->value ?? 3 }}" min="0" max="60" required>
                    <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:.78rem;color:#94a3b8;">min</span>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Minutes after acceptance before fee applies</small>
            </div>
        </div>
        <div class="info-box">
            <i class="fas fa-info-circle mr-1"></i>
            Logic: If driver has arrived → <strong>arrived fee</strong>. If accepted > free window → <strong>accepted fee</strong>. Otherwise → free.
        </div>
    </div>
</div>

{{-- ── Section 2: Waiting Time ─────────────────────────────────────────── --}}
<div class="fm-card">
    <div class="fm-header">
        <div class="fm-icon" style="background:#f59e0b;"><i class="fas fa-clock"></i></div>
        <div>
            <div class="fm-title">Waiting Time Charge</div>
            <div class="fm-subtitle">Added to the fare when passenger is late to board</div>
        </div>
    </div>
    <div class="fm-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="field-label">Free Waiting Window</div>
                <div class="input-wrap">
                    <input type="number" name="waiting_free_minutes" class="fm-input"
                           value="{{ $settings['waiting_free_minutes']->value ?? 3 }}" min="0" max="30" required>
                    <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:.78rem;color:#94a3b8;">min</span>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Minutes driver waits for free before meter starts</small>
            </div>
            <div class="col-md-6 mb-3">
                <div class="field-label">Per-Minute Rate</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="waiting_rate_khr_per_min" class="fm-input px"
                           value="{{ $settings['waiting_rate_khr_per_min']->value ?? 500 }}" min="0" step="100" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">KHR charged per minute after free window</small>
            </div>
        </div>
    </div>
</div>

{{-- ── Section 3: Night Surcharge & Speed ─────────────────────────────── --}}
<div class="fm-card">
    <div class="fm-header">
        <div class="fm-icon" style="background:#6366f1;"><i class="fas fa-moon"></i></div>
        <div>
            <div class="fm-title">Night Surcharge & Traffic Settings</div>
            <div class="fm-subtitle">Applied automatically between 22:00–05:00</div>
        </div>
    </div>
    <div class="fm-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="field-label">Night Surcharge — Rides</div>
                <div class="input-group">
                    <input type="number" name="night_surcharge_rate" class="form-control"
                           value="{{ $settings['night_surcharge_rate']->value ?? 0.20 }}" step="0.01" min="0" max="2" required>
                    <div class="input-group-append"><span class="input-group-text">×</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 0.20 = +20% on total fare</small>
            </div>
            <div class="col-md-3 mb-3">
                <div class="field-label">Night Surcharge — Delivery</div>
                <div class="input-group">
                    <input type="number" name="delivery_night_surcharge_rate" class="form-control"
                           value="{{ $settings['delivery_night_surcharge_rate']->value ?? 0.15 }}" step="0.01" min="0" max="2" required>
                    <div class="input-group-append"><span class="input-group-text">×</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 0.15 = +15%</small>
            </div>
            <div class="col-md-3 mb-3">
                <div class="field-label">Express Delivery Multiplier</div>
                <div class="input-group">
                    <input type="number" name="delivery_express_multiplier" class="form-control"
                           value="{{ $settings['delivery_express_multiplier']->value ?? 1.25 }}" step="0.05" min="1" max="5" required>
                    <div class="input-group-append"><span class="input-group-text">×</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 1.25 = +25% for express</small>
            </div>
            <div class="col-md-3 mb-3">
                <div class="field-label">Avg City Speed (fallback)</div>
                <div class="input-group">
                    <input type="number" name="avg_city_speed_kmh" class="form-control"
                           value="{{ $settings['avg_city_speed_kmh']->value ?? 30 }}" min="5" max="120" required>
                    <div class="input-group-append"><span class="input-group-text">km/h</span></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="field-label">Traffic Speed Threshold</div>
                <div class="input-group">
                    <input type="number" name="traffic_speed_threshold_kmh" class="form-control"
                           value="{{ $settings['traffic_speed_threshold_kmh']->value ?? 20 }}" min="5" max="60" required>
                    <div class="input-group-append"><span class="input-group-text">km/h</span></div>
                </div>
                <small class="text-muted" style="font-size:.72rem;">Per-minute rate activates below this</small>
            </div>
        </div>
    </div>
</div>

{{-- ── Section 4: Loyalty & Points ────────────────────────────────────── --}}
<div class="fm-card">
    <div class="fm-header">
        <div class="fm-icon" style="background:#10b981;"><i class="fas fa-star"></i></div>
        <div>
            <div class="fm-title">Loyalty Points & Redemption</div>
            <div class="fm-subtitle">Points earned per trip and redemption exchange rate</div>
        </div>
    </div>
    <div class="fm-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="field-label">Points Per Completed Ride</div>
                <input type="number" name="loyalty_points_per_ride" class="fm-input"
                       value="{{ $settings['loyalty_points_per_ride']->value ?? 10 }}" min="0" required>
            </div>
            <div class="col-md-3 mb-3">
                <div class="field-label">Points Per Completed Delivery</div>
                <input type="number" name="loyalty_points_per_delivery" class="fm-input"
                       value="{{ $settings['loyalty_points_per_delivery']->value ?? 8 }}" min="0" required>
            </div>
            <div class="col-md-3 mb-3">
                <div class="field-label">Minimum Points to Redeem</div>
                <input type="number" name="loyalty_min_redeem_points" class="fm-input"
                       value="{{ $settings['loyalty_min_redeem_points']->value ?? 100 }}" min="0" required>
            </div>
            <div class="col-md-3 mb-3">
                <div class="field-label">Redemption Rate (KHR per point)</div>
                <div class="input-wrap">
                    <span class="input-prefix">៛</span>
                    <input type="number" name="loyalty_redeem_rate_khr" class="fm-input px"
                           value="{{ $settings['loyalty_redeem_rate_khr']->value ?? 100 }}" min="0" step="50" required>
                </div>
                <small class="text-muted" style="font-size:.72rem;">e.g. 100 = 100 ៛ per loyalty point</small>
            </div>
        </div>
    </div>
</div>

{{-- ── Section 5: Membership Tiers ────────────────────────────────────── --}}
<div class="fm-card">
    <div class="fm-header">
        <div class="fm-icon" style="background:#f59e0b;"><i class="fas fa-crown"></i></div>
        <div>
            <div class="fm-title">Membership Tiers</div>
            <div class="fm-subtitle">Current tier thresholds and benefits (read-only overview)</div>
        </div>
    </div>
    <div class="fm-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="font-size:.82rem;">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th class="pl-4">Tier</th>
                        <th>Min Points</th>
                        <th>Ride Discount</th>
                        <th>Delivery Discount</th>
                        <th>Points Multiplier</th>
                        <th>Perks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tiers as $tier)
                    <tr>
                        <td class="pl-4">
                            <span class="tier-badge" style="background:{{ $tier->badge_color }}22;color:{{ $tier->badge_color }};">
                                <i class="{{ $tier->icon }}"></i> {{ $tier->name }}
                            </span>
                        </td>
                        <td>{{ number_format($tier->min_points) }}</td>
                        <td>{{ $tier->ride_discount_pct }}%</td>
                        <td>{{ $tier->delivery_discount_pct }}%</td>
                        <td>{{ $tier->points_multiplier }}×</td>
                        <td>
                            @if($tier->priority_support)<span class="badge badge-info mr-1" style="font-size:.68rem;">Priority Support</span>@endif
                            @if($tier->free_cancellations)<span class="badge badge-success" style="font-size:.68rem;">Free Cancels</span>@endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Save button ─────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-end mb-4">
    <button type="submit" class="btn btn-primary" style="border-radius:10px;padding:10px 32px;font-weight:600;">
        <i class="fas fa-save mr-2"></i> Save All Fee Settings
    </button>
</div>

</form>
@endsection
