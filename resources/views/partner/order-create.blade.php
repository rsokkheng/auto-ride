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
                <form method="POST" action="{{ route('partner.orders.store') }}">
                    @csrf

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
                    <h6 class="text-muted text-uppercase font-weight-bold mb-3" style="font-size:.7rem;letter-spacing:.1em;">Pickup &amp; Dropoff</h6>
                    <div class="form-group">
                        <label>Pickup Address <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_address" class="form-control @error('pickup_address') is-invalid @enderror"
                               value="{{ old('pickup_address') }}" placeholder="Enter full pickup address" required>
                        @error('pickup_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Pickup Latitude</label>
                            <input type="number" name="pickup_lat" class="form-control" step="any" value="{{ old('pickup_lat') }}" placeholder="11.5564">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Pickup Longitude</label>
                            <input type="number" name="pickup_lng" class="form-control" step="any" value="{{ old('pickup_lng') }}" placeholder="104.9282">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Dropoff Address <span class="text-danger">*</span></label>
                        <input type="text" name="dropoff_address" class="form-control @error('dropoff_address') is-invalid @enderror"
                               value="{{ old('dropoff_address') }}" placeholder="Enter full delivery address" required>
                        @error('dropoff_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Dropoff Latitude</label>
                            <input type="number" name="dropoff_lat" class="form-control" step="any" value="{{ old('dropoff_lat') }}" placeholder="11.5700">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Dropoff Longitude</label>
                            <input type="number" name="dropoff_lng" class="form-control" step="any" value="{{ old('dropoff_lng') }}" placeholder="104.9100">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="text-muted text-uppercase font-weight-bold mb-3" style="font-size:.7rem;letter-spacing:.1em;">Package &amp; Payment</h6>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Package Size</label>
                            <select name="package_size" class="form-control">
                                <option value="small"       {{ old('package_size')=='small'       ? 'selected' : '' }}>Small</option>
                                <option value="medium"      {{ old('package_size','medium')=='medium'      ? 'selected' : '' }}>Medium</option>
                                <option value="large"       {{ old('package_size')=='large'       ? 'selected' : '' }}>Large</option>
                                <option value="extra_large" {{ old('package_size')=='extra_large' ? 'selected' : '' }}>Extra Large</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Delivery Fee (KHR) <span class="text-danger">*</span></label>
                            <input type="number" name="fee" class="form-control @error('fee') is-invalid @enderror"
                                   value="{{ old('fee') }}" placeholder="5000" min="0" step="500" required>
                            @error('fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label>Fee Paid By</label>
                            <select name="payment_by" class="form-control">
                                <option value="recipient" {{ old('payment_by','recipient')=='recipient' ? 'selected' : '' }}>Recipient (COD)</option>
                                <option value="sender"    {{ old('payment_by')=='sender'    ? 'selected' : '' }}>Sender (Prepaid)</option>
                            </select>
                        </div>
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
