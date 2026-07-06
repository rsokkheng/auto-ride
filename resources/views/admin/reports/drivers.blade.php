@extends('admin.layout')
@section('title', 'Driver Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-id-badge mr-2 text-success"></i>Driver Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Drivers</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

<div class="card card-outline card-success mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'7 Days',30=>'30 Days',60=>'60 Days',90=>'90 Days'] as $d=>$l)
            <a href="{{ request()->fullUrlWithQuery(['period'=>$d]) }}" class="btn btn-sm {{ $period==$d?'btn-success':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.drivers'])
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ $totals->total ?? 0 }}</h3><p>Total Drivers</p></div>
            <div class="icon"><i class="fas fa-user-tie"></i></div>
            <div class="small-box-footer">New this period: {{ $newDrivers }}</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $totals->online ?? 0 }}</h3><p>Online Now</p></div>
            <div class="icon"><i class="fas fa-circle" style="color:#6ee7b7;"></i></div>
            <div class="small-box-footer">Approved: {{ $totals->approved ?? 0 }}</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $totals->pending ?? 0 }}</h3><p>Pending Approval</p></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
            <div class="small-box-footer"><a href="{{ route('admin.drivers') }}" class="text-white">Review →</a></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner"><h3>{{ number_format($totals->avg_rating ?? 0, 2) }}</h3><p>Avg Rating</p></div>
            <div class="icon"><i class="fas fa-star"></i></div>
            <div class="small-box-footer">All-time average</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Daily Completed Rides Trend</h3></div>
            <div class="card-body"><canvas id="driverTrendChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-outline card-danger">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i>Top Cancelling Drivers</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Driver</th><th class="text-center">Cancellations</th></tr></thead>
                    <tbody>
                        @forelse($cancellations as $c)
                        <tr>
                            <td><strong>{{ $c->name }}</strong></td>
                            <td class="text-center"><span class="badge badge-danger">{{ $c->cancelled }}</span></td>
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

<div class="card card-outline card-success">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-table mr-2"></i>Driver Activity Details</h3></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-dark">
                    <tr><th>#</th><th>Driver</th><th>Phone</th><th class="text-center">Status</th><th class="text-center">Rides</th><th class="text-center">Deliveries</th><th class="text-center">Total Jobs</th><th class="text-right">Gross Revenue</th><th class="text-center">Rating</th><th class="text-right">Wallet</th></tr>
                </thead>
                <tbody>
                    @forelse($driverActivity as $i => $d)
                    <tr>
                        <td>{{ $i+1 }}</td>
                        <td><strong>{{ $d->name }}</strong></td>
                        <td><small>{{ $d->phone }}</small></td>
                        <td class="text-center">
                            @if($d->approval_status==='pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($d->available)
                                <span class="badge badge-success">Online</span>
                            @else
                                <span class="badge badge-secondary">Offline</span>
                            @endif
                        </td>
                        <td class="text-center"><span class="badge badge-info">{{ $d->rides }}</span></td>
                        <td class="text-center"><span class="badge badge-primary">{{ $d->deliveries }}</span></td>
                        <td class="text-center font-weight-bold">{{ $d->rides + $d->deliveries }}</td>
                        <td class="text-right">{{ number_format($d->gross_revenue) }} ៛</td>
                        <td class="text-center">
                            @if($d->rating) <i class="fas fa-star text-warning"></i> {{ number_format($d->rating,1) }}
                            @else <span class="text-muted">—</span> @endif
                        </td>
                        <td class="text-right">{{ number_format($d->wallet_balance) }} ៛</td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center py-4 text-muted">No drivers found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
    var vals=dates.map(d=>data[d]?data[d].rides:0);
    new Chart(document.getElementById('driverTrendChart'),{
        type:'bar',
        data:{labels,datasets:[{label:'Completed Rides',data:vals,backgroundColor:'rgba(40,167,69,.7)',borderRadius:3}]},
        options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}
    });
})();
</script>
@endpush
