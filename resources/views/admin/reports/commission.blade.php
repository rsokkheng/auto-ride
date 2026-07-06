@extends('admin.layout')
@section('title', 'Commission Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-percentage mr-2 text-warning"></i>Commission Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Commission</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

<div class="card card-outline card-warning mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'7 Days',30=>'30 Days',60=>'60 Days',90=>'90 Days'] as $d=>$l)
            <a href="{{ request()->fullUrlWithQuery(['period'=>$d]) }}" class="btn btn-sm {{ $period==$d?'btn-warning':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
            @include('admin.reports.partials.scope-filter')
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.commission'])
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ number_format($totalCommission) }} <small style="font-size:.6em;">៛</small></h3><p>Total Commission</p></div>
            <div class="icon"><i class="fas fa-percentage"></i></div>
            <div class="small-box-footer">Platform earnings</div>
        </div>
    </div>
    <div class="col-lg-4 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $totalTrips }}</h3><p>Trips w/ Commission</p></div>
            <div class="icon"><i class="fas fa-route"></i></div>
            <div class="small-box-footer">Commissions collected</div>
        </div>
    </div>
    <div class="col-lg-4 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format($avgCommission) }} <small style="font-size:.6em;">៛</small></h3><p>Avg Per Trip</p></div>
            <div class="icon"><i class="fas fa-chart-bar"></i></div>
            <div class="small-box-footer">Average commission per trip</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Daily Commission Trend</h3></div>
            <div class="card-body"><canvas id="commChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title">Commission by Driver</h3></div>
            <div class="card-body p-0">
                @php $maxComm = $byDriver->max('commission') ?: 1; @endphp
                <ul class="list-group list-group-flush">
                    @foreach($byDriver as $d)
                    @php $pct = round(($d->commission/$maxComm)*100); @endphp
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span><strong>{{ $d->name }}</strong> <small class="text-muted">({{ $d->trips }} trips)</small></span>
                            <strong class="text-warning">{{ number_format($d->commission) }} ៛</strong>
                        </div>
                        <div class="progress" style="height:5px;"><div class="progress-bar bg-warning" style="width:{{ $pct }}%"></div></div>
                    </li>
                    @endforeach
                    @if($byDriver->isEmpty())
                    <li class="list-group-item text-center text-muted py-4">No commission data</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-warning">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-list mr-2"></i>Recent Commission Records</h3></div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-light">
                <tr><th>#</th><th>Driver</th><th class="text-right">Amount</th><th class="text-right">Before</th><th class="text-right">After</th><th>Note</th><th>Date</th></tr>
            </thead>
            <tbody>
                @foreach($recent as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td><strong>{{ $r->name }}</strong></td>
                    <td class="text-right font-weight-bold text-warning">{{ number_format($r->amount) }} ៛</td>
                    <td class="text-right text-muted"><small>{{ number_format($r->balance_before) }}</small></td>
                    <td class="text-right text-muted"><small>{{ number_format($r->balance_after) }}</small></td>
                    <td><small class="text-muted">{{ Str::limit($r->note,30) }}</small></td>
                    <td><small>{{ \Carbon\Carbon::parse($r->created_at)->format('d M H:i') }}</small></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function(){
    var dates=@json($trendDates), data=@json($daily);
    var labels=dates.map(d=>{var dt=new Date(d);return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short'});});
    var count=dates.map(d=>data[d]?data[d].c:0);
    var amounts=dates.map(d=>data[d]?data[d].total:0);
    new Chart(document.getElementById('commChart'),{
        type:'bar',
        data:{labels,datasets:[
            {label:'Trips',data:count,backgroundColor:'rgba(255,193,7,.5)',borderRadius:3,yAxisID:'y'},
            {label:'Commission (KHR)',data:amounts,type:'line',borderColor:'rgba(220,53,69,1)',backgroundColor:'transparent',yAxisID:'y1',tension:0.4},
        ]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}}}
    });
})();
</script>
@endpush
