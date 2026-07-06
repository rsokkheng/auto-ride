@extends('partner.layout')
@section('title', 'New Order')
@section('page-title', 'Create Delivery Order')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle text-danger mr-2"></i>New Delivery Order</h5>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('partner.orders.store') }}" id="order-form">
                    @csrf

                    {{-- Recipient --}}
                    <h6 class="text-muted text-uppercase font-weight-bold mb-3" style="font-size:.7rem;letter-spacing:.1em;">Recipient Info</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_name" class="form-control @error('recipient_name') is-invalid @enderror"
                                   value="{{ old('recipient_name') }}" placeholder="Full name" required>
                            @error('recipient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label>Recipient Phone <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_phone" class="form-control @error('recipient_phone') is-invalid @enderror"
                                   value="{{ old('recipient_phone') }}" placeholder="0XX XXX XXXX" required>
                            @error('recipient_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <hr class="my-3">
                    {{-- Pickup & Dropoff --}}
                    <h6 class="text-muted text-uppercase font-weight-bold mb-3" style="font-size:.7rem;letter-spacing:.1em;">Pickup &amp; Dropoff</h6>
                    <div class="form-group">
                        <label>Pickup Address <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_address" id="pickup_address"
                               class="form-control @error('pickup_address') is-invalid @enderror"
                               value="{{ old('pickup_address') }}" placeholder="Enter full pickup address" required>
                        @error('pickup_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Pickup Latitude</label>
                            <input type="number" name="pickup_lat" id="pickup_lat" class="form-control" step="any"
                                   value="{{ old('pickup_lat') }}" placeholder="11.5564" oninput="triggerEstimate()">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Pickup Longitude</label>
                            <input type="number" name="pickup_lng" id="pickup_lng" class="form-control" step="any"
                                   value="{{ old('pickup_lng') }}" placeholder="104.9282" oninput="triggerEstimate()">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Dropoff Address <span class="text-danger">*</span></label>
                        <input type="text" name="dropoff_address" id="dropoff_address"
                               class="form-control @error('dropoff_address') is-invalid @enderror"
                               value="{{ old('dropoff_address') }}" placeholder="Enter full delivery address" required>
                        @error('dropoff_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Dropoff Latitude</label>
                            <input type="number" name="dropoff_lat" id="dropoff_lat" class="form-control" step="any"
                                   value="{{ old('dropoff_lat') }}" placeholder="11.5700" oninput="triggerEstimate()">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Dropoff Longitude</label>
                            <input type="number" name="dropoff_lng" id="dropoff_lng" class="form-control" step="any"
                                   value="{{ old('dropoff_lng') }}" placeholder="104.9100" oninput="triggerEstimate()">
                        </div>
                    </div>

                    <hr class="my-3">
                    {{-- Package & Payment --}}
                    <h6 class="text-muted text-uppercase font-weight-bold mb-3" style="font-size:.7rem;letter-spacing:.1em;">Package &amp; Payment</h6>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Package Size</label>
                            <select name="package_size" id="package_size" class="form-control" onchange="triggerEstimate()">
                                <option value="small"  {{ old('package_size','small')=='small'  ? 'selected' : '' }}>Small</option>
                                <option value="medium" {{ old('package_size')=='medium' ? 'selected' : '' }}>Medium</option>
                                <option value="large"  {{ old('package_size')=='large'  ? 'selected' : '' }}>Large</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Package Value (KHR)</label>
                            <div class="input-group">
                                <input type="number" name="package_amount" class="form-control"
                                       value="{{ old('package_amount', 0) }}" min="0" step="1000"
                                       placeholder="0">
                                <div class="input-group-append"><span class="input-group-text">៛</span></div>
                            </div>
                            <small class="text-muted">Value of goods (for COD reference)</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Fee Paid By</label>
                            <select name="payment_by" class="form-control">
                                <option value="recipient" {{ old('payment_by','recipient')=='recipient' ? 'selected' : '' }}>Recipient (COD)</option>
                                <option value="sender"    {{ old('payment_by')=='sender'    ? 'selected' : '' }}>Sender (Prepaid)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Auto-calculated fee display --}}
                    <div id="fee-box" class="alert mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;display:none">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="small text-muted">Delivery Fee (auto-calculated from system pricing)</div>
                                <div class="h4 font-weight-bold text-success mb-0" id="fee-display">—</div>
                                <div class="small text-muted" id="fee-breakdown"></div>
                            </div>
                            <i class="fas fa-calculator fa-2x text-success" style="opacity:.3"></i>
                        </div>
                    </div>
                    <div id="fee-loading" class="text-muted small mb-3" style="display:none">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Calculating fee…
                    </div>
                    <div id="fee-error" class="alert alert-warning small mb-3" style="display:none">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Could not estimate fee — please ensure lat/lng are filled in, or the fee will be calculated on submit.
                    </div>

                    <div class="form-group">
                        <label>Partner Reference / Order No.</label>
                        <input type="text" name="partner_reference" class="form-control" value="{{ old('partner_reference') }}"
                               placeholder="Your internal order number (optional)">
                    </div>
                    <div class="form-group">
                        <label>Notes for Driver</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions (optional)">{{ old('notes') }}</textarea>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('partner.orders') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-qrcode mr-1"></i>Create Order &amp; Generate QR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var estimateTimer = null;

function triggerEstimate() {
    clearTimeout(estimateTimer);
    estimateTimer = setTimeout(fetchEstimate, 600);
}

function fetchEstimate() {
    var plat = parseFloat(document.getElementById('pickup_lat').value);
    var plng = parseFloat(document.getElementById('pickup_lng').value);
    var dlat = parseFloat(document.getElementById('dropoff_lat').value);
    var dlng = parseFloat(document.getElementById('dropoff_lng').value);
    var size = document.getElementById('package_size').value;

    if (isNaN(plat) || isNaN(plng) || isNaN(dlat) || isNaN(dlng)) return;

    document.getElementById('fee-box').style.display     = 'none';
    document.getElementById('fee-error').style.display   = 'none';
    document.getElementById('fee-loading').style.display = 'block';

    fetch('/api/v1/deliveries/estimate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            service_type: 'delivery',
            pickup_lat:   plat,
            pickup_lng:   plng,
            dropoff_lat:  dlat,
            dropoff_lng:  dlng,
            package_size: size,
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        document.getElementById('fee-loading').style.display = 'none';
        if (res && res.data && res.data.fare) {
            var fare = res.data.fare;
            var total = fare.total || 0;
            var dist  = fare.distance_km ? fare.distance_km.toFixed(1) + ' km' : '';
            document.getElementById('fee-display').textContent   = numberFmt(total) + ' ៛';
            document.getElementById('fee-breakdown').textContent = dist ? ('Distance: ' + dist) : '';
            document.getElementById('fee-box').style.display     = 'flex';
        } else {
            document.getElementById('fee-error').style.display = 'block';
        }
    })
    .catch(function() {
        document.getElementById('fee-loading').style.display = 'none';
        document.getElementById('fee-error').style.display   = 'block';
    });
}

function numberFmt(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>
@endpush
