@extends('admin.layout')
@section('title', 'Performance Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-tachometer-alt mr-2 text-primary"></i>Performance Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Performance</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'7 Days',30=>'30 Days',60=>'60 Days',90=>'90 Days'] as $d=>$l)
            <a href="{{ request()->fullUrlWithQuery(['period'=>$d]) }}" class="btn btn-sm {{ $period==$d?'btn-primary':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
        </form>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row">
    <div class="col-lg-6">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-car mr-2"></i>Ride KPIs</h3></div>
            <div class="card-body">
                @php
                $rTotal = $rideKpi->total ?? 0;
                $rDone  = $rideKpi->completed ?? 0;
                $rCancel= $rideKpi->cancelled ?? 0;
                $rRate  = $rTotal > 0 ? round(($rDone/$rTotal)*100,1) : 0;
                $cRate  = $rTotal > 0 ? round(($rCancel/$rTotal)*100,1) : 0;
                @endphp
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h3 text-info font-weight-bold">{{ $rTotal }}</div>
                        <small class="text-muted">Total Rides</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-success font-weight-bold">{{ $rRate }}%</div>
                        <small class="text-muted">Completion Rate</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-danger font-weight-bold">{{ $cRate }}%</div>
                        <small class="text-muted">Cancellation Rate</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h5">{{ $rideKpi->avg_duration ? round($rideKpi->avg_duration).' min' : '—' }}</div>
                        <small class="text-muted">Avg Duration</small>
                    </div>
                    <div class="col-4">
                        <div class="h5">{{ $rideKpi->avg_rating ? number_format($rideKpi->avg_rating,2) : '—' }}</div>
                        <small class="text-muted">Avg Rating</small>
                    </div>
                    <div class="col-4">
                        <div class="h5">{{ $rideKpi->avg_accept_seconds ? round($rideKpi->avg_accept_seconds/60,1).' min' : '—' }}</div>
                        <small class="text-muted">Avg Accept Time</small>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1"><small>Completion</small><small>{{ $rRate }}%</small></div>
                    <div class="progress mb-2"><div class="progress-bar bg-success" style="width:{{ $rRate }}%"></div></div>
                    <div class="d-flex justify-content-between mb-1"><small>Cancellation</small><small>{{ $cRate }}%</small></div>
                    <div class="progress"><div class="progress-bar bg-danger" style="width:{{ $cRate }}%"></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-box mr-2"></i>Delivery KPIs</h3></div>
            <div class="card-body">
                @php
                $dTotal  = $deliveryKpi->total ?? 0;
                $dDone   = $deliveryKpi->completed ?? 0;
                $dCancel = $deliveryKpi->cancelled ?? 0;
                $dRate   = $dTotal > 0 ? round(($dDone/$dTotal)*100,1) : 0;
                $dcRate  = $dTotal > 0 ? round(($dCancel/$dTotal)*100,1) : 0;
                @endphp
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h3 text-warning font-weight-bold">{{ $dTotal }}</div>
                        <small class="text-muted">Total Deliveries</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-success font-weight-bold">{{ $dRate }}%</div>
                        <small class="text-muted">Completion Rate</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-danger font-weight-bold">{{ $dcRate }}%</div>
                        <small class="text-muted">Cancellation Rate</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h5">{{ $deliveryKpi->avg_duration ? round($deliveryKpi->avg_duration).' min' : '—' }}</div>
                        <small class="text-muted">Avg Duration</small>
                    </div>
                    <div class="col-4">
                        <div class="h5">{{ $deliveryKpi->avg_rating ? number_format($deliveryKpi->avg_rating,2) : '—' }}</div>
                        <small class="text-muted">Avg Rating</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-danger">{{ $deliveryKpi->unassigned ?? 0 }}</div>
                        <small class="text-muted">Unassigned Now</small>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1"><small>Completion</small><small>{{ $dRate }}%</small></div>
                    <div class="progress mb-2"><div class="progress-bar bg-success" style="width:{{ $dRate }}%"></div></div>
                    <div class="d-flex justify-content-between mb-1"><small>Cancellation</small><small>{{ $dcRate }}%</small></div>
                    <div class="progress"><div class="progress-bar bg-danger" style="width:{{ $dcRate }}%"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Hourly Distribution --}}
    <div class="col-lg-6">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-clock mr-2"></i>Ride Activity by Hour</h3></div>
            <div class="card-body"><canvas id="hourlyChart" height="140"></canvas></div>
        </div>
    </div>
    {{-- Rating Distribution --}}
    <div class="col-lg-6">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-star mr-2"></i>Ride Rating Distribution</h3></div>
            <div class="card-body">
                @foreach([5,4,3,2,1] as $star)
                @php $cnt = $ratingDist[$star]->c ?? 0; $ratingTotal = $ratingDist->sum('c') ?: 1; $pct2=round(($cnt/$ratingTotal)*100); @endphp
                <div class="d-flex align-items-center mb-2">
                    <span style="width:60px;">{{ $star }} <i class="fas fa-star text-warning"></i></span>
                    <div class="progress flex-fill mx-2" style="height:18px;">
                        <div class="progress-bar bg-{{ $star>=4?'success':($star==3?'warning':'danger') }}" style="width:{{ $pct2 }}%">{{ $pct2 }}%</div>
                    </div>
                    <span style="width:30px;" class="text-muted small">{{ $cnt }}</span>
                </div>
                @endforeach
                <hr>
                <div class="text-center">
                    <strong class="text-warning" style="font-size:1.4rem;">{{ $rideKpi->avg_rating ? number_format($rideKpi->avg_rating,2) : '—' }}</strong>
                    <div class="text-muted small">Average rating</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Ride Cancel Reasons --}}
    <div class="col-lg-6">
        <div class="card card-outline card-danger">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-ban mr-2"></i>Ride Cancellation Reasons</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Reason</th><th class="text-center">Count</th></tr></thead>
                    <tbody>
                        @forelse($cancellationReasons as $r)
                        <tr>
                            <td>{{ $r->cancellation_reason ?: '(no reason)' }}</td>
                            <td class="text-center"><span class="badge badge-danger">{{ $r->c }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">No cancellations</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{-- Delivery Cancel Reasons --}}
    <div class="col-lg-6">
        <div class="card card-outline card-danger">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-box mr-2"></i>Delivery Cancellation Reasons</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Reason</th><th class="text-center">Count</th></tr></thead>
                    <tbody>
                        @forelse($deliCancelReasons as $r)
                        <tr>
                            <td>{{ $r->cancellation_reason ?: '(no reason)' }}</td>
                            <td class="text-center"><span class="badge badge-danger">{{ $r->c }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">No cancellations</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function(){
    var data=@json($hourly);
    var hours=Array.from({length:24},(_,i)=>i);
    var labels=hours.map(h=>(h<10?'0'+h:h)+':00');
    var vals=hours.map(h=>data[h]?data[h].c:0);
    var bgColors=hours.map(h=>{
        if(h>=7&&h<=9) return 'rgba(220,53,69,.7)';
        if(h>=11&&h<=13) return 'rgba(255,193,7,.7)';
        if(h>=17&&h<=20) return 'rgba(220,53,69,.7)';
        return 'rgba(23,162,184,.5)';
    });
    new Chart(document.getElementById('hourlyChart'),{
        type:'bar',
        data:{labels,datasets:[{label:'Completed Rides',data:vals,backgroundColor:bgColors,borderRadius:2}]},
        options:{responsive:true,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>' '+c.raw+' rides'}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}
    });
})();
</script>
@endpush
