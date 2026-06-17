<div class="row">
    <div class="form-group col-md-6">
        <label>Plan Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $plan->name ?? '') }}" required maxlength="80">
    </div>
    @unless(isset($plan))
    <div class="form-group col-md-6">
        <label>Slug <small class="text-muted">(unique identifier, e.g. basic-monthly)</small></label>
        <input type="text" name="slug" class="form-control" maxlength="40" required>
    </div>
    @endunless
    <div class="form-group col-md-12">
        <label>Description</label>
        <input type="text" name="description" class="form-control" value="{{ old('description', $plan->description ?? '') }}" maxlength="200">
    </div>
</div>

<div class="row">
    <div class="form-group col-md-6">
        <label>Price (KHR ៛) <span class="text-danger">*</span></label>
        <input type="number" name="price_khr" class="form-control" value="{{ old('price_khr', $plan->price_khr ?? '') }}" min="0" required>
    </div>
    <div class="form-group col-md-6">
        <label>Billing Cycle <span class="text-danger">*</span></label>
        <select name="billing_cycle" class="form-control" required>
            @foreach(['weekly', 'monthly', 'yearly'] as $cycle)
            <option value="{{ $cycle }}" {{ old('billing_cycle', $plan->billing_cycle ?? 'monthly') === $cycle ? 'selected' : '' }}>
                {{ ucfirst($cycle) }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<hr class="my-2">
<p class="text-muted small font-weight-bold mb-2">BENEFITS</p>

<div class="row">
    <div class="form-group col-md-4">
        <label>Ride Credit (KHR ៛/cycle)</label>
        <input type="number" name="ride_credit_khr" class="form-control" value="{{ old('ride_credit_khr', $plan->ride_credit_khr ?? 0) }}" min="0">
        <small class="text-muted">0 = no credit included</small>
    </div>
    <div class="form-group col-md-4">
        <label>Ride Discount (%)</label>
        <input type="number" name="ride_discount_pct" class="form-control" value="{{ old('ride_discount_pct', $plan->ride_discount_pct ?? 0) }}" min="0" max="100">
    </div>
    <div class="form-group col-md-4">
        <label>Delivery Discount (%)</label>
        <input type="number" name="delivery_discount_pct" class="form-control" value="{{ old('delivery_discount_pct', $plan->delivery_discount_pct ?? 0) }}" min="0" max="100">
    </div>
</div>

<div class="row">
    <div class="form-group col-md-4">
        <label>Free Cancellations</label>
        <input type="number" name="free_cancellations" class="form-control" value="{{ old('free_cancellations', $plan->free_cancellations ?? 0) }}" min="0">
        <small class="text-muted">0 = unlimited</small>
    </div>
    <div class="form-group col-md-4">
        <label>Bonus Loyalty Points (%)</label>
        <input type="number" name="bonus_points_pct" class="form-control" value="{{ old('bonus_points_pct', $plan->bonus_points_pct ?? 0) }}" min="0" max="200">
    </div>
    <div class="form-group col-md-4">
        <label>Sort Order</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0">
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="custom-control custom-switch mb-2">
            <input type="checkbox" class="custom-control-input" id="surge_waived{{ $plan->id ?? 'new' }}"
                   name="surge_waived" value="1" {{ old('surge_waived', ($plan->surge_waived ?? false) ? '1' : '0') ? 'checked' : '' }}>
            <label class="custom-control-label" for="surge_waived{{ $plan->id ?? 'new' }}">Surge Pricing Waived</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="custom-control custom-switch mb-2">
            <input type="checkbox" class="custom-control-input" id="priority_matching{{ $plan->id ?? 'new' }}"
                   name="priority_matching" value="1" {{ old('priority_matching', ($plan->priority_matching ?? false) ? '1' : '0') ? 'checked' : '' }}>
            <label class="custom-control-label" for="priority_matching{{ $plan->id ?? 'new' }}">Priority Driver Matching</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="custom-control custom-switch mb-2">
            <input type="checkbox" class="custom-control-input" id="active{{ $plan->id ?? 'new' }}"
                   name="active" value="1" {{ old('active', ($plan->active ?? true) ? '1' : '0') ? 'checked' : '' }}>
            <label class="custom-control-label" for="active{{ $plan->id ?? 'new' }}">Active</label>
        </div>
    </div>
</div>

<hr class="my-2">
<p class="text-muted small font-weight-bold mb-2">APPEARANCE</p>

<div class="row">
    <div class="form-group col-md-6">
        <label>Badge Color</label>
        <div class="input-group">
            <input type="color" name="badge_color" class="form-control" style="height:38px;padding:2px"
                   value="{{ old('badge_color', $plan->badge_color ?? '#6366f1') }}">
        </div>
    </div>
    <div class="form-group col-md-6">
        <label>Icon <small class="text-muted">(Font Awesome class)</small></label>
        <input type="text" name="icon" class="form-control" value="{{ old('icon', $plan->icon ?? 'fas fa-star') }}" placeholder="fas fa-crown">
    </div>
</div>
