<div class="form-group">
    <label>Airport Name</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $zone->name ?? '') }}" required maxlength="100">
</div>
@unless(isset($zone))
<div class="form-group">
    <label>IATA Code <small class="text-muted">(3–4 letters, e.g. PNH)</small></label>
    <input type="text" name="iata_code" class="form-control text-uppercase" maxlength="4" required>
</div>
@endunless
<div class="form-row">
    <div class="form-group col-6">
        <label>Latitude</label>
        <input type="number" step="0.0000001" name="latitude" class="form-control" value="{{ old('latitude', $zone->latitude ?? '') }}" required>
    </div>
    <div class="form-group col-6">
        <label>Longitude</label>
        <input type="number" step="0.0000001" name="longitude" class="form-control" value="{{ old('longitude', $zone->longitude ?? '') }}" required>
    </div>
</div>
<div class="form-group">
    <label>Detection Radius (meters)</label>
    <input type="number" name="radius_meters" class="form-control" value="{{ old('radius_meters', $zone->radius_meters ?? 2000) }}" min="100" max="10000" required>
</div>
<div class="form-row">
    <div class="form-group col-6">
        <label>Airport Surcharge (KHR ៛)</label>
        <input type="number" name="surcharge_khr" class="form-control" value="{{ old('surcharge_khr', $zone->surcharge_khr ?? 5000) }}" min="0" required>
    </div>
    <div class="form-group col-6">
        <label>Luggage Fee / bag (KHR ៛)</label>
        <input type="number" name="luggage_fee_khr" class="form-control" value="{{ old('luggage_fee_khr', $zone->luggage_fee_khr ?? 2000) }}" min="0" required>
    </div>
</div>
<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="active{{ $zone->id ?? 'new' }}" name="active" value="1"
            {{ old('active', ($zone->active ?? true) ? '1' : '0') ? 'checked' : '' }}>
        <label class="custom-control-label" for="active{{ $zone->id ?? 'new' }}">Active</label>
    </div>
</div>
