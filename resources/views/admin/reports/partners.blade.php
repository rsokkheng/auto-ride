@extends('admin.layout')
@section('title', 'Partner Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-handshake mr-2 text-warning"></i>Partner Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Partners</li></ol></div>
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
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $totalPartners }}</h3><p>Total Partners</p></div>
            <div class="icon"><i class="fas fa-handshake"></i></div>
            <div class="small-box-footer">Active partners</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format($totalOrders) }}</h3><p>Partner Orders</p></div>
            <div class="icon"><i class="fas fa-box"></i></div>
            <div class="small-box-footer">In period</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format($totalRevenue) }} <small>៛</small></h3><p>Partner Revenue</p></div>
            <div class="icon"><i class="fas fa-coins"></i></div>
            <div class="small-box-footer">Completed orders only</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $totalOrders > 0 ? number_format($totalRevenue / $totalOrders) : 0 }} <small>៛</small></h3>
                <p>Avg Order Value</p>
            </div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
            <div class="small-box-footer">Per order average</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Daily Partner Orders Trend</h3></div>
            <div class="card-body"><canvas id="partnerTrend" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title">Partner Overview</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Partner</th><th>Orders</th><th>Rate</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody>
                        @foreach($partners as $p)
                        @php $rate=$p->orders>0?round(($p->done/$p->orders)*100):0; @endphp
                        <tr>
                            <td><strong>{{ $p->name }}</strong><br><small class="text-muted">{{ $p->phone }}</small></td>
                            <td>{{ $p->orders }}</td>
                            <td><span class="badge badge-{{ $rate>=80?'success':($rate>=50?'warning':'danger') }}">{{ $rate }}%</span></td>
                            <td class="text-right font-weight-bold">{{ number_format($p->revenue) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-warning">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-table mr-2"></i>Partner Detail</h3></div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-dark">
                <tr><th>#</th><th>Partner</th><th>Phone</th><th>Joined</th><th class="text-center">Orders</th><th class="text-center">Done</th><th class="text-center">Cancelled</th><th class="text-center">Express</th><th class="text-center">Success %</th><th class="text-right">Revenue</th><th class="text-right">Wallet</th><th>Contract Fee</th></tr>
            </thead>
            <tbody>
                @forelse($partners as $i => $p)
                @php $rate=$p->orders>0?round(($p->done/$p->orders)*100):0; @endphp
                <tr>
                    <td>{{ $i+1 }}</td>
                    <td><strong>{{ $p->name }}</strong></td>
                    <td>{{ $p->phone }}</td>
                    <td><small>{{ \Carbon\Carbon::parse($p->joined)->format('d M Y') }}</small></td>
                    <td class="text-center font-weight-bold">{{ $p->orders }}</td>
                    <td class="text-center text-success">{{ $p->done }}</td>
                    <td class="text-center text-danger">{{ $p->cancelled }}</td>
                    <td class="text-center text-warning">{{ $p->express }}</td>
                    <td class="text-center"><span class="badge badge-{{ $rate>=80?'success':($rate>=50?'warning':'danger') }}">{{ $rate }}%</span></td>
                    <td class="text-right font-weight-bold">{{ number_format($p->revenue) }} ៛</td>
                    <td class="text-right">{{ number_format($p->wallet_balance) }} ៛</td>
                    <td>{{ $p->contract_normal_fee ? number_format($p->contract_normal_fee).' ៛' : '<span class="text-muted">No contract</span>' }}</td>
                </tr>
                @empty
                <tr><td colspan="12" class="text-center py-4 text-muted">No partners found</td></tr>
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
    var dates=@json($trendDates), data=@json($daily);
    var labels=dates.map(d=>{var dt=new Date(d);return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short'});});
    var orders=dates.map(d=>data[d]?data[d].c:0);
    var rev=dates.map(d=>data[d]?data[d].rev:0);
    new Chart(document.getElementById('partnerTrend'),{
        type:'bar',
        data:{labels,datasets:[{label:'Orders',data:orders,backgroundColor:'rgba(255,193,7,.7)',borderRadius:3,yAxisID:'y'},{label:'Revenue (KHR)',data:rev,type:'line',borderColor:'rgba(40,167,69,1)',backgroundColor:'transparent',yAxisID:'y1',tension:0.4}]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}}}
    });
})();
</script>
@endpush
