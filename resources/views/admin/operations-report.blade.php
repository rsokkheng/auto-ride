@extends('admin.layout')
@section('title', 'Operations Report')

@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6">
        <h1 class="m-0">Operations Report</h1>
    </div>
    <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">Operations Report</li>
        </ol>
    </div>
</div>
@endsection

@section('content')
<div class="container-fluid">

{{-- ── Period Filter ─────────────────────────────────────────────── --}}
<div class="card card-outline card-primary mb-4">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'Last 7 Days', 30=>'Last 30 Days', 60=>'Last 60 Days', 90=>'Last 90 Days'] as $d => $label)
            <a href="{{ request()->fullUrlWithQuery(['period' => $d]) }}"
               class="btn btn-sm {{ $period == $d ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
            @endforeach
            <span class="text-muted small ml-3">From {{ $start->format('d M Y') }} → Today</span>
            @include('admin.reports.partials.scope-filter')
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.operations'])
        </form>
    </div>
</div>

{{-- ── Summary Cards ────────────────────────────────────────────────── --}}
<div class="row">
    {{-- Deliveries --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ number_format($totalDeliveries) }}</h3>
                <p>Total Deliveries</p>
            </div>
            <div class="icon"><i class="fas fa-box"></i></div>
            <div class="small-box-footer d-flex justify-content-between px-2">
                <span><i class="fas fa-check-circle mr-1"></i>{{ $doneDeliveries }} done</span>
                <span><i class="fas fa-times-circle mr-1"></i>{{ $cancelDeliveries }} cancelled</span>
            </div>
        </div>
    </div>
    {{-- Rides --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ number_format($totalRides) }}</h3>
                <p>Total Rides</p>
            </div>
            <div class="icon"><i class="fas fa-car"></i></div>
            <div class="small-box-footer d-flex justify-content-between px-2">
                <span><i class="fas fa-check-circle mr-1"></i>{{ $doneRides }} done</span>
                <span><i class="fas fa-times-circle mr-1"></i>{{ $cancelRides }} cancelled</span>
            </div>
        </div>
    </div>
    {{-- Revenue --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ number_format($deliveryRevenue + $rideRevenue) }} <small>៛</small></h3>
                <p>Total Revenue</p>
            </div>
            <div class="icon"><i class="fas fa-coins"></i></div>
            <div class="small-box-footer d-flex justify-content-between px-2">
                <span>Delivery: {{ number_format($deliveryRevenue) }}</span>
                <span>Rides: {{ number_format($rideRevenue) }}</span>
            </div>
        </div>
    </div>
    {{-- Drivers --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $activeDrivers }}<small>/{{ $totalDrivers }}</small></h3>
                <p>Active Drivers</p>
            </div>
            <div class="icon"><i class="fas fa-id-badge"></i></div>
            <div class="small-box-footer d-flex justify-content-between px-2">
                <span>{{ $totalPartners }} Partners</span>
                @if($unassigned > 0)
                <span class="text-white font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>{{ $unassigned }} unassigned</span>
                @else
                <span>All assigned</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Row 2: Secondary Metrics ─────────────────────────────────────── --}}
<div class="row">
    <div class="col-md-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-percentage"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Delivery Success Rate</span>
                <span class="info-box-number">
                    @php $dRate = $totalDeliveries > 0 ? round(($doneDeliveries/$totalDeliveries)*100,1) : 0; @endphp
                    {{ $dRate }}%
                </span>
                <div class="progress"><div class="progress-bar bg-info" style="width:{{ $dRate }}%"></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-percentage"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Ride Completion Rate</span>
                <span class="info-box-number">
                    @php $rRate = $totalRides > 0 ? round(($doneRides/$totalRides)*100,1) : 0; @endphp
                    {{ $rRate }}%
                </span>
                <div class="progress"><div class="progress-bar bg-success" style="width:{{ $rRate }}%"></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-warning"><i class="fas fa-building"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Platform Commission</span>
                <span class="info-box-number">{{ number_format($commission) }} ៛</span>
                <span class="progress-description">Collected this period</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-danger"><i class="fas fa-money-bill-wave"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pending COD</span>
                <span class="info-box-number">{{ number_format($codPending) }} ៛</span>
                <span class="progress-description">
                    @if($avgDeliveryMin)
                        Avg delivery: {{ round($avgDeliveryMin) }} min
                    @else
                        No delivery time data
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 3: Chart + Delivery Status ────────────────────────────────── --}}
<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Daily Order Trend (Last 30 Days)</h3>
                <div class="card-tools">
                    <div class="d-flex gap-2">
                        <span class="badge badge-info px-3 py-2">
                            <i class="fas fa-box mr-1"></i>Deliveries
                        </span>
                        <span class="badge badge-success px-3 py-2 ml-2">
                            <i class="fas fa-car mr-1"></i>Rides
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="120"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Delivery Status Breakdown</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @php
                    $statusColors = [
                        'completed'  => 'success',
                        'delivered'  => 'success',
                        'in_transit' => 'primary',
                        'picked_up'  => 'info',
                        'assigned'   => 'warning',
                        'accepted'   => 'info',
                        'created'    => 'secondary',
                        'cancelled'  => 'danger',
                    ];
                    $totalForPct = $deliveryStatuses->sum('c') ?: 1;
                    @endphp
                    @foreach($deliveryStatuses as $status => $row)
                    @php $pct = round(($row->c / $totalForPct) * 100, 1); @endphp
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>
                                <span class="badge badge-{{ $statusColors[$status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
                            </span>
                            <span class="font-weight-bold">{{ $row->c }} <small class="text-muted">({{ $pct }}%)</small></span>
                        </div>
                        <div class="progress" style="height:4px;">
                            <div class="progress-bar bg-{{ $statusColors[$status] ?? 'secondary' }}" style="width:{{ $pct }}%"></div>
                        </div>
                    </li>
                    @endforeach
                    @if($deliveryStatuses->isEmpty())
                    <li class="list-group-item text-muted text-center py-4">No deliveries in this period</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 4: Partner Performance + Driver Leaderboard ──────────────── --}}
<div class="row">
    <div class="col-lg-6">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-handshake mr-2"></i>Partner Performance</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Partner</th>
                            <th class="text-center">Orders</th>
                            <th class="text-center">Done</th>
                            <th class="text-center">Cancel</th>
                            <th class="text-center">Rate</th>
                            <th class="text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($partnerStats as $p)
                        @php $pRate = $p->orders > 0 ? round(($p->done/$p->orders)*100) : 0; @endphp
                        <tr>
                            <td class="font-weight-bold">{{ $p->name }}</td>
                            <td class="text-center">{{ $p->orders }}</td>
                            <td class="text-center text-success">{{ $p->done }}</td>
                            <td class="text-center text-danger">{{ $p->cancelled }}</td>
                            <td class="text-center">
                                <span class="badge badge-{{ $pRate >= 80 ? 'success' : ($pRate >= 50 ? 'warning' : 'danger') }}">
                                    {{ $pRate }}%
                                </span>
                            </td>
                            <td class="text-right font-weight-bold">{{ number_format($p->revenue) }} ៛</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No partner orders in this period</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-trophy mr-2"></i>Driver Leaderboard</h3>
                <div class="card-tools">
                    <small class="text-muted">Top 10 by completed jobs</small>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Driver</th>
                            <th class="text-center">Deliveries</th>
                            <th class="text-center">Rides</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($driverLeaderboard as $i => $d)
                        <tr>
                            <td>
                                @if($i == 0) <span class="badge badge-warning"><i class="fas fa-crown"></i></span>
                                @elseif($i == 1) <span class="badge badge-secondary">2</span>
                                @elseif($i == 2) <span class="badge" style="background:#cd7f32;color:#fff;">3</span>
                                @else <span class="text-muted">{{ $i+1 }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="font-weight-bold">{{ $d->name }}</div>
                                <small class="text-muted">{{ $d->phone }}</small>
                            </td>
                            <td class="text-center"><span class="badge badge-info">{{ $d->deliveries }}</span></td>
                            <td class="text-center"><span class="badge badge-success">{{ $d->rides }}</span></td>
                            <td class="text-center font-weight-bold">{{ $d->deliveries + $d->rides }}</td>
                            <td class="text-center">
                                @if($d->rating)
                                <span class="text-warning"><i class="fas fa-star"></i></span> {{ number_format($d->rating,1) }}
                                @else <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No driver activity in this period</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 5: Revenue Breakdown + Recent Cancellations ─────────────── --}}
<div class="row">
    <div class="col-lg-4">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-money-bill mr-2"></i>Revenue Breakdown</h3>
            </div>
            <div class="card-body">
                @php
                $totalRev = $deliveryRevenue + $rideRevenue;
                $dPct     = $totalRev > 0 ? round(($deliveryRevenue/$totalRev)*100) : 0;
                $rPct     = $totalRev > 0 ? round(($rideRevenue/$totalRev)*100) : 0;
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="fas fa-box text-info mr-1"></i>Delivery Revenue</span>
                        <strong>{{ number_format($deliveryRevenue) }} ៛</strong>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-info" style="width:{{ $dPct }}%">{{ $dPct }}%</div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="fas fa-car text-success mr-1"></i>Ride Revenue</span>
                        <strong>{{ number_format($rideRevenue) }} ៛</strong>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" style="width:{{ $rPct }}%">{{ $rPct }}%</div>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total Gross Revenue</strong>
                    <strong class="text-primary">{{ number_format($totalRev) }} ៛</strong>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="text-muted">Platform Commission</span>
                    <span class="font-weight-bold text-success">{{ number_format($commission) }} ៛</span>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span class="text-muted">Pending COD Collection</span>
                    <span class="font-weight-bold text-warning">{{ number_format($codPending) }} ៛</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2 text-danger"></i>Recent Cancelled Deliveries</h3>
                <div class="card-tools">
                    <small class="text-muted">{{ $recentCancelled->count() }} in this period</small>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Recipient</th>
                            <th>Driver</th>
                            <th>Reason</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentCancelled as $c)
                        <tr>
                            <td>
                                <a href="{{ route('admin.deliveries') }}?search={{ $c->id }}" class="font-weight-bold text-decoration-none">
                                    #{{ $c->id }}
                                </a>
                            </td>
                            <td>{{ $c->recipient_name }}</td>
                            <td>{{ $c->driver_name ?? '<span class="text-muted">Unassigned</span>' }}</td>
                            <td>
                                @if($c->cancellation_reason)
                                    <span class="text-danger">{{ Str::limit($c->cancellation_reason, 40) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><small>{{ \Carbon\Carbon::parse($c->created_at)->format('d M H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">
                            <i class="fas fa-check-circle text-success mr-2"></i>No cancellations in this period
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($unassigned > 0)
            <div class="card-footer bg-warning-light">
                <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                <strong>{{ $unassigned }}</strong> delivery orders are currently unassigned.
                <a href="{{ route('admin.deliveries') }}" class="ml-2 btn btn-xs btn-warning">View Deliveries</a>
            </div>
            @endif
        </div>
    </div>
</div>

</div>{{-- /container-fluid --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var dates = @json($trendDates);
    var dData = dates.map(function(d) {
        var r = @json($deliveryTrend);
        return r[d] ? r[d].c : 0;
    });
    var rData = dates.map(function(d) {
        var r = @json($rideTrend);
        return r[d] ? r[d].c : 0;
    });
    var labels = dates.map(function(d) {
        var dt = new Date(d);
        return dt.toLocaleDateString('en-GB', {day:'2-digit', month:'short'});
    });

    var ctx = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Deliveries',
                    data: dData,
                    backgroundColor: 'rgba(23,162,184,0.7)',
                    borderColor: 'rgba(23,162,184,1)',
                    borderWidth: 1,
                    borderRadius: 3,
                },
                {
                    label: 'Rides',
                    data: rData,
                    backgroundColor: 'rgba(40,167,69,0.7)',
                    borderColor: 'rgba(40,167,69,1)',
                    borderWidth: 1,
                    borderRadius: 3,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                x: { stacked: false, grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
})();
</script>
@endpush
