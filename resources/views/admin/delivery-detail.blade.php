@extends('admin.layout')
@section('title', (($delivery->service_type ?? 'delivery') === 'moving' ? 'Moving Order' : 'Delivery') . ' #' . $delivery->id)
@section('page-title', (($delivery->service_type ?? 'delivery') === 'moving' ? 'Moving Order' : 'Delivery Order') . ' #' . $delivery->id)

@section('content')
<div class="container-fluid">

    {{-- Top Key Financial & Status Summary Cards --}}
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-white shadow-sm border">
                <span class="info-box-icon bg-primary text-white"><i class="fas fa-money-bill-wave"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Customer Fee (ថ្លៃសេវា)</span>
                    <span class="info-box-number text-primary" style="font-size:1.25rem;">
                        {{ number_format($delivery->fee ?? 0) }} ៛
                    </span>
                    <small class="text-muted">ថ្លៃសេវា booking delivery</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-white shadow-sm border">
                <span class="info-box-icon bg-info text-white"><i class="fas fa-box-open"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Package Amount (COD)</span>
                    <span class="info-box-number text-dark" style="font-size:1.25rem;">
                        @if(($delivery->package_amount ?? 0) > 0)
                            {{ number_format($delivery->package_amount) }} ៛
                        @else
                            <span class="text-muted" style="font-size:1rem;">0 ៛ (គ្មាន)</span>
                        @endif
                    </span>
                    <small class="text-muted">តម្លៃទំនិញជាក់ស្ដែង</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-white shadow-sm border">
                <span class="info-box-icon bg-success text-white"><i class="fas fa-wallet"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Net Driver (អ្នកដឹក)</span>
                    <span class="info-box-number text-success" style="font-size:1.25rem;">
                        {{ number_format($netDriver ?? 0) }} ៛
                    </span>
                    <small class="text-muted">ចំណូលសុទ្ធ (កាត់ {{ $driverCommRate }}% Comm)</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-white shadow-sm border">
                <span class="info-box-icon bg-warning text-dark"><i class="fas fa-credit-card"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Payment &amp; By</span>
                    <span class="info-box-number text-dark" style="font-size:1rem;">
                        {{ ucfirst($delivery->payment_method ?? 'cash') }}
                        <span class="badge badge-{{ ($delivery->payment_status ?? 'unpaid') === 'paid' ? 'success' : 'secondary' }}">
                            {{ ucfirst($delivery->payment_status ?? 'unpaid') }}
                        </span>
                    </span>
                    <small class="text-muted">Paid by: <strong>{{ ucfirst($delivery->payment_by ?? 'sender') }}</strong></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        {{-- Left column --}}
        <div class="col-lg-8">

            {{-- Route Timeline --}}
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-route mr-2 text-primary"></i>Delivery Route &amp; Locations</h3>
                    @if($delivery->service_option === 'express')
                        <span class="badge badge-danger float-right"><i class="fas fa-bolt mr-1"></i>Express Service</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0" style="padding: 0;">

                        {{-- PICKUP POINT --}}
                        <li style="display:flex;align-items:stretch;padding:0;">
                            <div style="display:flex;flex-direction:column;align-items:center;width:56px;flex-shrink:0;padding:16px 0;">
                                <div style="width:38px;height:38px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                                    <i class="fas fa-arrow-up text-white" style="font-size:.9rem;"></i>
                                </div>
                                <div style="width:2px;background:#cbd5e1;flex:1;margin-top:4px;"></div>
                            </div>
                            <div style="padding:14px 18px 14px 8px;flex:1;border-bottom:1px solid #f1f5f9;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.8px;color:#059669;font-weight:700;">
                                        <i class="fas fa-map-marker-alt mr-1"></i>Pickup Point (ទីតាំងទទួល)
                                    </div>
                                    @if($delivery->pickup_scanned_at)
                                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Scanned: {{ $delivery->pickup_scanned_at->format('d M Y H:i') }}</span>
                                    @endif
                                </div>
                                <div style="font-size:1rem;font-weight:600;color:#1e293b;margin-top:4px;">
                                    {{ $delivery->pickup_address }}
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-user text-muted mr-1"></i><strong>Sender:</strong> {{ $delivery->sender_name ?? $delivery->sender?->name ?? '—' }}
                                    @if($delivery->sender_phone ?? $delivery->sender?->phone)
                                        &nbsp;•&nbsp; <i class="fas fa-phone text-muted mr-1"></i>{{ $delivery->sender_phone ?? $delivery->sender?->phone }}
                                    @endif
                                </div>
                                @if($delivery->pickup_lat && $delivery->pickup_lng)
                                    <div style="font-size:.75rem;color:#64748b;margin-top:3px;">
                                        <i class="fas fa-compass mr-1"></i>GPS: {{ $delivery->pickup_lat }}, {{ $delivery->pickup_lng }}
                                    </div>
                                @endif
                            </div>
                        </li>

                        {{-- INTERMEDIATE STOPS (IF ANY) --}}
                        @if($delivery->stops && $delivery->stops->count() > 0)
                            @foreach($delivery->stops as $i => $stop)
                                <li style="display:flex;align-items:stretch;padding:0;">
                                    <div style="display:flex;flex-direction:column;align-items:center;width:56px;flex-shrink:0;padding:16px 0;">
                                        <div style="width:34px;height:34px;border-radius:50%;background:{{ $stop->arrived_at ? '#f59e0b' : '#fef3c7' }};border:2px solid #f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;">
                                            <span style="font-size:.8rem;font-weight:700;color:#b45309;">{{ $i + 1 }}</span>
                                        </div>
                                        <div style="width:2px;background:#cbd5e1;flex:1;margin-top:4px;"></div>
                                    </div>
                                    <div style="padding:12px 18px 12px 8px;flex:1;border-bottom:1px solid #f1f5f9;">
                                        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.8px;color:#d97706;font-weight:700;">
                                            Stop {{ $i + 1 }}
                                            @if($stop->arrived_at)
                                                <span class="badge badge-success ml-1">Arrived: {{ \Carbon\Carbon::parse($stop->arrived_at)->format('H:i') }}</span>
                                            @endif
                                        </div>
                                        <div style="font-size:.95rem;font-weight:600;color:#1e293b;margin-top:2px;">
                                            {{ $stop->address }}
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        @endif

                        {{-- DROPOFF POINT --}}
                        <li style="display:flex;align-items:stretch;padding:0;">
                            <div style="display:flex;flex-direction:column;align-items:center;width:56px;flex-shrink:0;padding:16px 0;">
                                <div style="width:38px;height:38px;border-radius:50%;background:#ef4444;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                                    <i class="fas fa-arrow-down text-white" style="font-size:.9rem;"></i>
                                </div>
                            </div>
                            <div style="padding:14px 18px 14px 8px;flex:1;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.8px;color:#dc2626;font-weight:700;">
                                        <i class="fas fa-map-marker-alt mr-1"></i>Dropoff Point (ទីតាំងដឹកទៅដល់)
                                    </div>
                                    @if($delivery->delivery_scanned_at)
                                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Scanned: {{ $delivery->delivery_scanned_at->format('d M Y H:i') }}</span>
                                    @endif
                                </div>
                                <div style="font-size:1rem;font-weight:600;color:#1e293b;margin-top:4px;">
                                    {{ $delivery->dropoff_address }}
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-user text-muted mr-1"></i><strong>Recipient:</strong> {{ $delivery->recipient_name ?? '—' }}
                                    @if($delivery->recipient_phone)
                                        &nbsp;•&nbsp; <i class="fas fa-phone text-muted mr-1"></i>{{ $delivery->recipient_phone }}
                                    @endif
                                </div>
                                @if($delivery->dropoff_lat && $delivery->dropoff_lng)
                                    <div style="font-size:.75rem;color:#64748b;margin-top:3px;">
                                        <i class="fas fa-compass mr-1"></i>GPS: {{ $delivery->dropoff_lat }}, {{ $delivery->dropoff_lng }}
                                    </div>
                                @endif
                            </div>
                        </li>

                    </ul>
                </div>

                @if($delivery->pickup_lat && $delivery->dropoff_lat)
                <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="fas fa-location-arrow mr-1"></i>Route coordinates available</span>
                    <a href="https://www.google.com/maps/dir/{{ $delivery->pickup_lat }},{{ $delivery->pickup_lng }}/{{ $delivery->dropoff_lat }},{{ $delivery->dropoff_lng }}"
                       target="_blank" class="btn btn-sm btn-outline-primary font-weight-bold">
                        <i class="fas fa-map-marked-alt mr-1"></i>Open in Google Maps
                    </a>
                </div>
                @endif
            </div>

            {{-- Package / Moving Information Card --}}
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-boxes mr-2 text-secondary"></i>Package &amp; Service Details</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width:140px;">Service Type:</td>
                                    <td>
                                        @if(($delivery->service_type ?? 'delivery') === 'moving')
                                            <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-truck-moving mr-1"></i>Moving</span>
                                        @else
                                            <span class="badge badge-primary px-2 py-1"><i class="fas fa-box mr-1"></i>Standard Delivery</span>
                                        @endif
                                        @if($delivery->service_option === 'express')
                                            <span class="badge badge-danger px-2 py-1 ml-1"><i class="fas fa-bolt mr-1"></i>Express</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Package Size:</td>
                                    <td>
                                        @php
                                            $psMap = [
                                                'small'       => ['label' => 'Small (Backpack / ឯកសារ)', 'color' => 'success'],
                                                'medium'      => ['label' => 'Medium (Car Boot / កេសមធ្យម)', 'color' => 'warning'],
                                                'large'       => ['label' => 'Large (Van / កេសធំ)', 'color' => 'danger'],
                                                'extra_large' => ['label' => 'Extra Large (Truck / ធំពិសេស)', 'color' => 'dark'],
                                            ];
                                            $pInfo = $psMap[$delivery->package_size ?? 'small'] ?? ['label' => ucfirst($delivery->package_size ?? '—'), 'color' => 'secondary'];
                                        @endphp
                                        <span class="badge badge-{{ $pInfo['color'] }} px-2 py-1">{{ $pInfo['label'] }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Package Value (COD):</td>
                                    <td>
                                        @if(($delivery->package_amount ?? 0) > 0)
                                            <strong class="text-dark">{{ number_format($delivery->package_amount) }} ៛</strong>
                                            <small class="text-muted d-block">តម្លៃទំនិញសម្រាប់ប្រមូល / COD</small>
                                        @else
                                            <span class="text-muted">0 ៛ (គ្មានតម្លៃទំនិញ)</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($delivery->package_details)
                                <tr>
                                    <td class="text-muted">Item Description:</td>
                                    <td><strong>{{ $delivery->package_details }}</strong></td>
                                </tr>
                                @endif
                            </table>
                        </div>

                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                @if(($delivery->service_type ?? 'delivery') === 'moving')
                                <tr>
                                    <td class="text-muted" style="width:140px;">Moving Floors:</td>
                                    <td>
                                        Floor {{ $delivery->floor_pickup ?? 0 }} → Floor {{ $delivery->floor_dropoff ?? 0 }}
                                        @if($delivery->has_elevator)
                                            <span class="badge badge-success ml-1">Elevator Available</span>
                                        @else
                                            <span class="badge badge-danger ml-1">Stairs (No Elevator)</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Helpers &amp; Items:</td>
                                    <td>
                                        {{ $delivery->requires_helpers ?? 0 }} helper(s) ({{ ucfirst(str_replace('_', ' ', $delivery->helper_type ?? 'normal_carry')) }})
                                        @if($delivery->heavy_items)
                                            <span class="badge badge-danger ml-1">Heavy Items</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif

                                @if($delivery->partner_reference)
                                <tr>
                                    <td class="text-muted">Partner Reference:</td>
                                    <td><span class="badge badge-light border font-weight-bold">{{ $delivery->partner_reference }}</span></td>
                                </tr>
                                @endif

                                @if($delivery->notes)
                                <tr>
                                    <td class="text-muted">Special Notes:</td>
                                    <td><em class="text-muted">{{ $delivery->notes }}</em></td>
                                </tr>
                                @endif

                                @if($delivery->cancellation_reason)
                                <tr>
                                    <td class="text-muted">Cancel Reason:</td>
                                    <td><span class="text-danger font-weight-bold">{{ $delivery->cancellation_reason }}</span></td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if($delivery->proof_photo)
                        <hr>
                        <div>
                            <div class="font-weight-bold mb-2"><i class="fas fa-camera mr-1 text-primary"></i>Delivery Proof Photo</div>
                            <a href="{{ asset('storage/' . $delivery->proof_photo) }}" target="_blank">
                                <img src="{{ asset('storage/' . $delivery->proof_photo) }}" alt="Delivery Proof"
                                     style="max-width:240px;max-height:180px;border-radius:8px;border:1px solid #e2e8f0;object-fit:cover;">
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- People & Entities Involved Card --}}
            <div class="card card-outline card-info shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-users mr-2 text-info"></i>Sender, Recipient &amp; Driver</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Sender / Customer --}}
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded border h-100">
                                <div class="text-uppercase small font-weight-bold text-primary mb-2">
                                    <i class="fas fa-user mr-1"></i>Sender (អ្នកផ្ញើ)
                                </div>
                                <div class="font-weight-bold" style="font-size:1.05rem;">
                                    {{ $delivery->sender_name ?? $delivery->sender?->name ?? '—' }}
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-phone mr-1"></i>{{ $delivery->sender_phone ?? $delivery->sender?->phone ?? '—' }}
                                </div>
                                @if($delivery->sender?->email)
                                    <div class="text-muted small">
                                        <i class="fas fa-envelope mr-1"></i>{{ $delivery->sender->email }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Recipient --}}
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded border h-100">
                                <div class="text-uppercase small font-weight-bold text-danger mb-2">
                                    <i class="fas fa-user-tag mr-1"></i>Recipient (អ្នកទទួល)
                                </div>
                                <div class="font-weight-bold" style="font-size:1.05rem;">
                                    {{ $delivery->recipient_name ?? '—' }}
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-phone mr-1"></i>{{ $delivery->recipient_phone ?? '—' }}
                                </div>
                                <div class="text-muted small mt-1 text-truncate" title="{{ $delivery->dropoff_address }}">
                                    <i class="fas fa-map-marker-alt mr-1"></i>{{ Str::limit($delivery->dropoff_address, 30) }}
                                </div>
                            </div>
                        </div>

                        {{-- Driver --}}
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded border h-100">
                                <div class="text-uppercase small font-weight-bold text-success mb-2">
                                    <i class="fas fa-motorcycle mr-1"></i>Driver (អ្នកដឹកជញ្ជូន)
                                </div>
                                @if($delivery->driver)
                                    <div class="font-weight-bold" style="font-size:1.05rem;">
                                        {{ $delivery->driver->name }}
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-phone mr-1"></i>{{ $delivery->driver->phone ?? '—' }}
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Type: <span class="badge badge-secondary">{{ ucfirst($delivery->driver->driver_type ?? 'Owner') }}</span>
                                        @if($delivery->driver->company)
                                            <span class="badge badge-info">{{ $delivery->driver->company->name }}</span>
                                        @endif
                                    </div>
                                    @if($delivery->vehicle)
                                        <div class="small text-muted mt-1">
                                            <i class="fas fa-car mr-1"></i>{{ $delivery->vehicle->plate_number }} ({{ $delivery->vehicle->model }})
                                        </div>
                                    @endif
                                @else
                                    <div class="text-muted font-italic mb-2">Unassigned (មិនទាន់មានអ្នកដឹក)</div>
                                    <button class="btn btn-xs btn-success" onclick="openAssignModal()">
                                        <i class="fas fa-user-plus mr-1"></i>Assign Driver
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($delivery->partner)
                    <div class="alert alert-light border mb-0 mt-2 py-2">
                        <i class="fas fa-handshake text-info mr-2"></i>
                        Partner Order by <strong>{{ $delivery->partner->name }}</strong> ({{ $delivery->partner->phone ?? 'No phone' }})
                        @if($delivery->partner_reference)
                            &nbsp;•&nbsp; Ref: <code>{{ $delivery->partner_reference }}</code>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Right column --}}
        <div class="col-lg-4">

            {{-- Financial Breakdown Card --}}
            <div class="card card-outline card-success shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-calculator mr-2 text-success"></i>Financial Breakdown (គណនាថ្លៃ)</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        {{-- Customer Fee --}}
                        <tr class="bg-light">
                            <td class="pl-3 py-2 font-weight-bold text-primary">
                                <i class="fas fa-money-bill mr-1"></i>Customer Fee (ថ្លៃសេវា)
                            </td>
                            <td class="text-right pr-3 py-2 font-weight-bold text-primary" style="font-size:1.05rem;">
                                {{ number_format($delivery->fee ?? 0) }} ៛
                            </td>
                        </tr>

                        {{-- Package Amount --}}
                        <tr>
                            <td class="pl-3 text-muted">Package Amount / COD (តម្លៃទំនិញ)</td>
                            <td class="text-right pr-3 font-weight-bold text-dark">
                                {{ number_format($delivery->package_amount ?? 0) }} ៛
                            </td>
                        </tr>

                        {{-- Moving Breakdown --}}
                        @if(($delivery->service_type ?? 'delivery') === 'moving')
                            @if(($delivery->helper_fee ?? 0) > 0)
                            <tr>
                                <td class="pl-3 text-muted">Helper Fee</td>
                                <td class="text-right pr-3">{{ number_format($delivery->helper_fee) }} ៛</td>
                            </tr>
                            @endif
                            @if(($delivery->floor_fee ?? 0) > 0)
                            <tr>
                                <td class="pl-3 text-muted">Floor Fee</td>
                                <td class="text-right pr-3">{{ number_format($delivery->floor_fee) }} ៛</td>
                            </tr>
                            @endif
                        @endif

                        {{-- Surge / Express --}}
                        @if(($delivery->surge_multiplier ?? 1.0) > 1)
                        <tr>
                            <td class="pl-3 text-muted">Surge Multiplier</td>
                            <td class="text-right pr-3 text-danger">×{{ $delivery->surge_multiplier }}</td>
                        </tr>
                        @endif

                        @if($delivery->service_option === 'express' && ($delivery->express_multiplier ?? 1) > 1)
                        <tr>
                            <td class="pl-3 text-muted">Express Multiplier</td>
                            <td class="text-right pr-3 text-danger">×{{ $delivery->express_multiplier }}</td>
                        </tr>
                        @endif

                        @if(($delivery->discount_amount ?? 0) > 0)
                        <tr>
                            <td class="pl-3 text-muted">Promo Discount</td>
                            <td class="text-right pr-3 text-success">- {{ number_format($delivery->discount_amount) }} ៛</td>
                        </tr>
                        @endif

                        {{-- Commission --}}
                        <tr style="border-top:1px dashed #cbd5e1;">
                            <td class="pl-3 text-muted">
                                Platform Commission ({{ $driverCommRate }}%)
                            </td>
                            <td class="text-right pr-3 text-danger">
                                - {{ number_format($platformFee) }} ៛
                            </td>
                        </tr>

                        {{-- Driver Net --}}
                        <tr class="bg-success text-white font-weight-bold" style="font-size:1.05rem;">
                            <td class="pl-3 py-2">
                                <i class="fas fa-hand-holding-usd mr-1"></i>Net Driver (ចំណូលអ្នកដឹក)
                            </td>
                            <td class="text-right pr-3 py-2">
                                {{ number_format($netDriver) }} ៛
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="card-footer bg-light p-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Payment Model:</span>
                        <span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $delivery->payment_model ?? 'customer_pays')) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Payment Method:</span>
                        <span class="font-weight-bold small">{{ ucfirst($delivery->payment_method ?? 'cash') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Paid By:</span>
                        <span class="font-weight-bold small">{{ ucfirst($delivery->payment_by ?? 'sender') }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Payment Status:</span>
                        <span class="badge badge-{{ ($delivery->payment_status ?? 'unpaid') === 'paid' ? 'success' : 'secondary' }}">
                            {{ ucfirst($delivery->payment_status ?? 'unpaid') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Timeline & Dates Card --}}
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-clock mr-2 text-secondary"></i>Order Timeline</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="pl-3 text-muted" style="width:130px;">Created:</td>
                            <td class="text-right pr-3">{{ $delivery->created_at->format('d M Y H:i:s') }}</td>
                        </tr>
                        @if($delivery->scheduled_at)
                        <tr>
                            <td class="pl-3 text-muted">Scheduled:</td>
                            <td class="text-right pr-3 font-weight-bold text-primary">{{ $delivery->scheduled_at->format('d M Y H:i') }}</td>
                        </tr>
                        @endif
                        @if($delivery->assigned_at)
                        <tr>
                            <td class="pl-3 text-muted">Assigned:</td>
                            <td class="text-right pr-3 text-success">{{ $delivery->assigned_at->format('d M Y H:i') }}</td>
                        </tr>
                        @endif
                        @if($delivery->started_at)
                        <tr>
                            <td class="pl-3 text-muted">Started:</td>
                            <td class="text-right pr-3">{{ $delivery->started_at->format('d M Y H:i') }}</td>
                        </tr>
                        @endif
                        @if($delivery->completed_at)
                        <tr>
                            <td class="pl-3 text-muted">Completed:</td>
                            <td class="text-right pr-3 font-weight-bold text-success">{{ $delivery->completed_at->format('d M Y H:i') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Customer Rating Card --}}
            @if($delivery->rating)
            <div class="card card-outline card-warning shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-star mr-2 text-warning"></i>Customer Rating</h3>
                </div>
                <div class="card-body text-center">
                    <div style="font-size:2.2rem;color:#f59e0b;font-weight:700;">{{ number_format($delivery->rating, 1) }}</div>
                    <div>
                        @for($s = 1; $s <= 5; $s++)
                            <i class="fas fa-star{{ $s <= round($delivery->rating) ? '' : '-o' }} text-warning"></i>
                        @endfor
                    </div>
                    @if($delivery->rating_comment)
                        <p class="text-muted mt-2 mb-0 font-italic small">"{{ $delivery->rating_comment }}"</p>
                    @endif
                </div>
            </div>
            @endif

            {{-- Action Buttons --}}
            <div class="d-flex flex-column gap-2 mb-4" style="gap:8px;">
                @if(!in_array($delivery->status, ['completed', 'cancelled']))
                <form method="POST" action="{{ route('admin.deliveries.complete', $delivery) }}"
                      onsubmit="return confirm('Mark order #{{ $delivery->id }} as completed?')">
                    @csrf
                    <button class="btn btn-success btn-block font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Mark as Completed
                    </button>
                </form>
                @endif

                <a href="{{ route('admin.deliveries', ['type' => $delivery->service_type ?? 'delivery']) }}"
                   class="btn btn-outline-secondary btn-block">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Deliveries List
                </a>
            </div>

        </div>
    </div>
</div>

{{-- Assign Driver Modal (if unassigned) --}}
@if(!$delivery->driver)
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Assign Driver</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="{{ route('admin.deliveries.assign', $delivery) }}">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Select Driver <span class="text-danger">*</span></label>
                        <select name="driver_id" class="form-control" required>
                            <option value="">— Choose a driver —</option>
                            @php
                                $drivers = \App\Models\User::where('role', 'driver')->orderBy('name')->get();
                            @endphp
                            @foreach($drivers as $dr)
                                <option value="{{ $dr->id }}">{{ $dr->name }}{{ $dr->phone ? ' — '.$dr->phone : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-user-check mr-1"></i>Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openAssignModal() {
    $('#assignModal').modal('show');
}
</script>
@endif

@endsection
