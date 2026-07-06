@extends('admin.layout')
@section('title', 'Customer Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-users mr-2 text-primary"></i>Customer Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Customers</li></ol></div>
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
            @include('admin.reports.partials.scope-filter')
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.customers'])
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner"><h3>{{ number_format($totals->total) }}</h3><p>Total Customers</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="small-box-footer">All registered passengers</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ $newCustomers }}</h3><p>New Customers</p></div>
            <div class="icon"><i class="fas fa-user-plus"></i></div>
            <div class="small-box-footer">Registered in period</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $activeCustomers }}</h3><p>Active Customers</p></div>
            <div class="icon"><i class="fas fa-user-check"></i></div>
            <div class="small-box-footer">Made trips/orders in period</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $totals->total > 0 ? round(($activeCustomers/$totals->total)*100,1) : 0 }}<small>%</small></h3>
                <p>Engagement Rate</p>
            </div>
            <div class="icon"><i class="fas fa-chart-pie"></i></div>
            <div class="small-box-footer">Active / Total</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>New Customer Registrations</h3></div>
            <div class="card-body"><canvas id="customerChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-outline card-info h-100">
            <div class="card-header"><h3 class="card-title">Retention Overview</h3></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div style="font-size:3rem;font-weight:700;color:#17a2b8;">{{ $activeCustomers }}</div>
                    <div class="text-muted">Active in this period</div>
                </div>
                <div class="progress mb-2" style="height:20px;">
                    @php $er = $totals->total>0?round(($activeCustomers/$totals->total)*100,1):0; @endphp
                    <div class="progress-bar bg-info" style="width:{{ $er }}%">{{ $er }}% Active</div>
                </div>
                <div class="progress mb-2" style="height:20px;">
                    @php $nr = $totals->total>0?round(($newCustomers/$totals->total)*100,1):0; @endphp
                    <div class="progress-bar bg-success" style="width:{{ $nr }}%">{{ $nr }}% New</div>
                </div>
                <small class="text-muted d-block mt-2">
                    {{ $totals->total - $activeCustomers }} customers inactive this period
                </small>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-trophy mr-2"></i>Top Customers by Rides</h3></div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-dark">
                <tr><th>#</th><th>Customer</th><th>Phone</th><th>Joined</th><th class="text-center">Rides</th><th class="text-right">Total Spent</th><th class="text-center">Avg Rating Given</th></tr>
            </thead>
            <tbody>
                @forelse($topCustomers as $i => $c)
                <tr>
                    <td>
                        @if($i==0)<span class="badge badge-warning"><i class="fas fa-crown"></i></span>
                        @elseif($i==1)<span class="badge badge-secondary">2</span>
                        @elseif($i==2)<span class="badge" style="background:#cd7f32;color:#fff;">3</span>
                        @else {{ $i+1 }} @endif
                    </td>
                    <td><strong>{{ $c->name }}</strong></td>
                    <td>{{ $c->phone }}</td>
                    <td><small>{{ \Carbon\Carbon::parse($c->joined)->format('d M Y') }}</small></td>
                    <td class="text-center"><span class="badge badge-info">{{ $c->rides }}</span></td>
                    <td class="text-right font-weight-bold">{{ number_format($c->spent) }} ៛</td>
                    <td class="text-center">
                        @if($c->avg_rating_given) <i class="fas fa-star text-warning"></i> {{ number_format($c->avg_rating_given,1) }}
                        @else <span class="text-muted">—</span> @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">No customer data available</td></tr>
                @endforelse
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
    var dates=@json($trendDates), data=@json($registrations);
    var labels=dates.map(d=>{var dt=new Date(d);return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short'});});
    var vals=dates.map(d=>data[d]?data[d].c:0);
    new Chart(document.getElementById('customerChart'),{
        type:'bar',
        data:{labels,datasets:[{label:'New Customers',data:vals,backgroundColor:'rgba(0,123,255,.7)',borderRadius:3}]},
        options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}
    });
})();
</script>
@endpush
