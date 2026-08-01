@extends('admin.layout')
@section('title', 'Ride #' . $ride->id)
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6">
        <h1 class="m-0">
            <i class="fas fa-car mr-2 text-primary"></i>Ride #{{ $ride->id }}
            @php
                $sc = [
                    'requested'   => 'secondary',
                    'pending'     => 'warning',
                    'accepted'    => 'info',
                    'driver_arrived' => 'info',
                    'in_progress' => 'primary',
                    'completed'   => 'success',
                    'cancelled'   => 'danger',
                ];
                $cls = $sc[$ride->status] ?? 'secondary';
            @endphp
            <span class="badge badge-{{ $cls }} ml-2" style="font-size:.55em;vertical-align:middle;">
                {{ ucfirst(str_replace('_', ' ', $ride->status)) }}
            </span>
        </h1>
    </div>
    <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.rides') }}">Rides</a></li>
            <li class="breadcrumb-item active">#{{ $ride->id }}</li>
        </ol>
    </div>
</div>
@endsection

@section('content')
<div class="container-fluid">
<div class="row">

    {{-- Left column --}}
    <div class="col-lg-8">

        {{-- Route Timeline --}}
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-route mr-2"></i>Route
                    @php $stopCount = $ride->stops->count(); @endphp
                    @if($stopCount > 0)
                    <span class="badge badge-warning ml-2">{{ $stopCount }} intermediate stop{{ $stopCount > 1 ? 's' : '' }}</span>
                    @endif
                </h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-unstyled mb-0" style="padding: 0;">

                    {{-- PICKUP --}}
                    <li style="display:flex;align-items:stretch;padding:0;">
                        <div style="display:flex;flex-direction:column;align-items:center;width:52px;flex-shrink:0;padding:16px 0;">
                            <div style="width:36px;height:36px;border-radius:50%;background:#28a745;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;">
                                <i class="fas fa-map-marker-alt text-white" style="font-size:.9rem;"></i>
                            </div>
                            @if($stopCount > 0 || $ride->dropoff_address)
                            <div style="width:2px;background:#dee2e6;flex:1;margin-top:4px;"></div>
                            @endif
                        </div>
                        <div style="padding:14px 16px 14px 8px;flex:1;border-bottom:1px solid #f1f5f9;">
                            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;color:#6c757d;font-weight:600;">Pickup</div>
                            <div style="font-size:.95rem;font-weight:600;color:#1a202c;margin-top:2px;">
                                {{ $ride->pickup_address }}
                            </div>
                            @if($ride->pickup_lat)
                            <div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">
                                {{ $ride->pickup_lat }}, {{ $ride->pickup_lng }}
                            </div>
                            @endif
                            @if($ride->accepted_at)
                            <div style="font-size:.75rem;color:#6c757d;margin-top:3px;">
                                <i class="fas fa-clock mr-1"></i>Accepted: {{ \Carbon\Carbon::parse($ride->accepted_at)->format('d M Y H:i') }}
                            </div>
                            @endif
                        </div>
                    </li>

                    {{-- INTERMEDIATE STOPS --}}
                    @foreach($ride->stops as $i => $stop)
                    @php $isLast = $loop->last && !$ride->dropoff_address; @endphp
                    <li style="display:flex;align-items:stretch;padding:0;">
                        <div style="display:flex;flex-direction:column;align-items:center;width:52px;flex-shrink:0;padding:16px 0;">
                            <div style="width:32px;height:32px;border-radius:50%;background:{{ $stop->arrived_at ? '#fd7e14' : '#fff3cd' }};border:2px solid #fd7e14;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;">
                                <span style="font-size:.75rem;font-weight:700;color:#fd7e14;">{{ $i + 1 }}</span>
                            </div>
                            @if(!$isLast)
                            <div style="width:2px;background:#dee2e6;flex:1;margin-top:4px;"></div>
                            @endif
                        </div>
                        <div style="padding:12px 16px 12px 8px;flex:1;border-bottom:1px solid #f1f5f9;">
                            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;color:#fd7e14;font-weight:600;">
                                Stop {{ $i + 1 }}
                                @if($stop->arrived_at)
                                <span class="badge badge-success ml-1" style="font-size:.65rem;">Arrived</span>
                                @endif
                            </div>
                            <div style="font-size:.92rem;font-weight:600;color:#1a202c;margin-top:2px;">
                                {{ $stop->address }}
                            </div>
                            @if($stop->lat)
                            <div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">
                                {{ $stop->lat }}, {{ $stop->lng }}
                            </div>
                            @endif
                            @if($stop->arrived_at)
                            <div style="font-size:.75rem;color:#6c757d;margin-top:3px;">
                                <i class="fas fa-check-circle text-success mr-1"></i>Arrived: {{ \Carbon\Carbon::parse($stop->arrived_at)->format('d M Y H:i') }}
                            </div>
                            @endif
                        </div>
                    </li>
                    @endforeach

                    {{-- FINAL DROPOFF --}}
                    @if($ride->dropoff_address)
                    <li style="display:flex;align-items:stretch;padding:0;">
                        <div style="display:flex;flex-direction:column;align-items:center;width:52px;flex-shrink:0;padding:16px 0;">
                            <div style="width:36px;height:36px;border-radius:50%;background:#dc3545;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-flag-checkered text-white" style="font-size:.85rem;"></i>
                            </div>
                        </div>
                        <div style="padding:14px 16px 14px 8px;flex:1;">
                            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;color:#dc3545;font-weight:600;">
                                Final Dropoff
                                @if($ride->completed_at)
                                <span class="badge badge-success ml-1" style="font-size:.65rem;">Completed</span>
                                @endif
                            </div>
                            <div style="font-size:.95rem;font-weight:600;color:#1a202c;margin-top:2px;">
                                {{ $ride->dropoff_address }}
                            </div>
                            @if($ride->dropoff_lat)
                            <div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">
                                {{ $ride->dropoff_lat }}, {{ $ride->dropoff_lng }}
                            </div>
                            @endif
                            @if($ride->completed_at)
                            <div style="font-size:.75rem;color:#6c757d;margin-top:3px;">
                                <i class="fas fa-check-circle text-success mr-1"></i>Completed: {{ \Carbon\Carbon::parse($ride->completed_at)->format('d M Y H:i') }}
                            </div>
                            @endif
                        </div>
                    </li>
                    @else
                    <li style="padding:12px 16px 12px 60px;">
                        <span class="text-muted" style="font-size:.85rem;font-style:italic;">
                            <i class="fas fa-question-circle mr-1"></i>Destination not set — metered ride
                        </span>
                    </li>
                    @endif

                </ul>
            </div>
            @if($ride->pickup_lat && $ride->dropoff_lat)
            <div class="card-footer p-0">
                <a href="https://www.google.com/maps/dir/{{ $ride->pickup_lat }},{{ $ride->pickup_lng }}/{{ $ride->stops->map(fn($s)=>"{$s->lat},{$s->lng}")->implode('/') }}/{{ $ride->dropoff_lat }},{{ $ride->dropoff_lng }}"
                   target="_blank" class="btn btn-sm btn-outline-primary m-2">
                    <i class="fas fa-map-marked-alt mr-1"></i>Open in Google Maps
                </a>
            </div>
            @endif
        </div>

        {{-- Ride Details --}}
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Ride Details</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width:130px;">Passenger</td>
                                <td><strong>{{ $ride->passenger?->name ?? '—' }}</strong>
                                    @if($ride->passenger?->phone)
                                    <div style="font-size:.8rem;color:#6c757d;">{{ $ride->passenger->phone }}</div>
                                    @endif
                                    @if($ride->passenger_name)
                                    <div style="font-size:.8rem;color:#6c757d;">Booked for: {{ $ride->passenger_name }} {{ $ride->passenger_phone }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Driver</td>
                                <td>
                                    @if($ride->driver)
                                    <strong>{{ $ride->driver->name }}</strong>
                                    <div style="font-size:.8rem;color:#6c757d;">{{ $ride->driver->phone }}</div>
                                    @else
                                    <span class="text-muted">Unassigned</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Service Type</td>
                                <td><span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $ride->service_type ?? 'standard')) }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Payment</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $ride->payment_method ?? 'cash')) }}
                                    <span class="badge badge-{{ $ride->payment_status === 'paid' ? 'success' : 'warning' }} ml-1">
                                        {{ ucfirst($ride->payment_status ?? 'unpaid') }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width:130px;">Created</td>
                                <td>{{ $ride->created_at->format('d M Y H:i') }}</td>
                            </tr>
                            @if($ride->scheduled_at)
                            <tr>
                                <td class="text-muted">Scheduled</td>
                                <td>{{ \Carbon\Carbon::parse($ride->scheduled_at)->format('d M Y H:i') }}</td>
                            </tr>
                            @endif
                            @if($ride->started_at)
                            <tr>
                                <td class="text-muted">Started</td>
                                <td>{{ \Carbon\Carbon::parse($ride->started_at)->format('d M Y H:i') }}</td>
                            </tr>
                            @endif
                            @if($ride->surge_multiplier > 1)
                            <tr>
                                <td class="text-muted">Surge</td>
                                <td><span class="badge badge-danger">×{{ $ride->surge_multiplier }}</span></td>
                            </tr>
                            @endif
                            @if($ride->notes)
                            <tr>
                                <td class="text-muted">Notes</td>
                                <td><small class="text-muted">{{ $ride->notes }}</small></td>
                            </tr>
                            @endif
                            @if($ride->cancellation_reason)
                            <tr>
                                <td class="text-muted">Cancel Reason</td>
                                <td><small class="text-danger">{{ $ride->cancellation_reason }}</small></td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Right column --}}
    <div class="col-lg-4">

        {{-- Fare Summary --}}
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-coins mr-2"></i>Fare Summary</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="pl-3">Base Fare</td>
                        <td class="text-right pr-3">{{ number_format($ride->fare ?? 0) }} ៛</td>
                    </tr>
                    @if($ride->waiting_fee > 0)
                    <tr>
                        <td class="pl-3">Waiting Fee</td>
                        <td class="text-right pr-3 text-warning">+ {{ number_format($ride->waiting_fee) }} ៛</td>
                    </tr>
                    @endif
                    @if($ride->discount_amount > 0)
                    <tr>
                        <td class="pl-3">Promo Discount</td>
                        <td class="text-right pr-3 text-success">- {{ number_format($ride->discount_amount) }} ៛</td>
                    </tr>
                    @endif
                    @if($ride->cancellation_fee > 0)
                    <tr>
                        <td class="pl-3">Cancellation Fee</td>
                        <td class="text-right pr-3 text-danger">{{ number_format($ride->cancellation_fee) }} ៛</td>
                    </tr>
                    @endif
                    @if($ride->tip_amount > 0)
                    <tr>
                        <td class="pl-3">Tip</td>
                        <td class="text-right pr-3 text-info">+ {{ number_format($ride->tip_amount) }} ៛</td>
                    </tr>
                    @endif
                    <tr class="font-weight-bold" style="border-top:2px solid #dee2e6;">
                        <td class="pl-3">Total</td>
                        <td class="text-right pr-3 text-success" style="font-size:1.1rem;">
                            {{ number_format($ride->fare ?? 0) }} ៛
                        </td>
                    </tr>
                </table>
                @if($ride->fare == 0)
                <div class="text-center py-2 text-muted" style="font-size:.8rem;font-style:italic;">
                    <i class="fas fa-tachometer-alt mr-1"></i>Metered ride — fare set at completion
                </div>
                @endif
            </div>
        </div>

        {{-- Stop Count Summary --}}
        @if($stopCount > 0)
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-map-signs mr-2"></i>Stop Summary</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr><th>#</th><th>Address</th><th>Arrived</th></tr>
                    </thead>
                    <tbody>
                        @foreach($ride->stops as $i => $stop)
                        <tr>
                            <td><span class="badge badge-warning">{{ $i + 1 }}</span></td>
                            <td style="font-size:.82rem;">{{ $stop->address }}</td>
                            <td>
                                @if($stop->arrived_at)
                                <span class="text-success" style="font-size:.75rem;"><i class="fas fa-check"></i> {{ \Carbon\Carbon::parse($stop->arrived_at)->format('H:i') }}</span>
                                @else
                                <span class="text-muted" style="font-size:.75rem;">Pending</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Rating --}}
        @if($ride->rating)
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-star mr-2 text-warning"></i>Rating</h3></div>
            <div class="card-body text-center">
                <div style="font-size:2rem;color:#f59e0b;font-weight:700;">{{ number_format($ride->rating, 1) }}</div>
                <div>
                    @for($s = 1; $s <= 5; $s++)
                    <i class="fas fa-star{{ $s <= round($ride->rating) ? '' : '-o' }} text-warning"></i>
                    @endfor
                </div>
                @if($ride->rating_comment)
                <p class="text-muted mt-2 mb-0" style="font-size:.85rem;font-style:italic;">"{{ $ride->rating_comment }}"</p>
                @endif
            </div>
        </div>
        @endif

        {{-- Back button --}}
        <a href="{{ route('admin.rides') }}" class="btn btn-outline-secondary btn-block">
            <i class="fas fa-arrow-left mr-2"></i>Back to Rides
        </a>

    </div>
</div>
</div>
@endsection
