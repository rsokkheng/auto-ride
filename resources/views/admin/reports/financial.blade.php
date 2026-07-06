@extends('admin.layout')
@section('title', 'Financial Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-dollar-sign mr-2 text-success"></i>Financial Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Financial</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

<div class="card card-outline card-success mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'7 Days',30=>'30 Days',60=>'60 Days',90=>'90 Days'] as $d=>$l)
            <a href="{{ request()->fullUrlWithQuery(['period'=>$d]) }}" class="btn btn-sm {{ $period==$d?'btn-success':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.financial'])
        </form>
    </div>
</div>

@php $totalRev = $rideRev + $deliveryRev; @endphp

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format($totalRev) }} <small style="font-size:.6em;">៛</small></h3><p>Total Gross Revenue</p></div>
            <div class="icon"><i class="fas fa-coins"></i></div>
            <div class="small-box-footer">Rides + Deliveries</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format($commission) }} <small style="font-size:.6em;">៛</small></h3><p>Platform Commission</p></div>
            <div class="icon"><i class="fas fa-building"></i></div>
            <div class="small-box-footer">Net platform income</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ number_format($topups) }} <small style="font-size:.6em;">៛</small></h3><p>Top-ups Approved</p></div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
            <div class="small-box-footer">Tips: {{ number_format($tips) }} ៛</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ number_format($withdrawals) }} <small style="font-size:.6em;">៛</small></h3><p>Withdrawals Paid</p></div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="small-box-footer">COD Pending: {{ number_format($cod) }} ៛</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-area mr-2"></i>Daily Revenue Trend</h3></div>
            <div class="card-body"><canvas id="finChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title">Revenue Breakdown</h3></div>
            <div class="card-body">
                @php
                $rPct = $totalRev>0?round(($rideRev/$totalRev)*100):0;
                $dPct = $totalRev>0?round(($deliveryRev/$totalRev)*100):0;
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="fas fa-car text-info mr-2"></i>Ride Revenue</span>
                        <strong>{{ number_format($rideRev) }} ៛</strong>
                    </div>
                    <div class="progress"><div class="progress-bar bg-info" style="width:{{ $rPct }}%">{{ $rPct }}%</div></div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="fas fa-box text-warning mr-2"></i>Delivery Revenue</span>
                        <strong>{{ number_format($deliveryRev) }} ៛</strong>
                    </div>
                    <div class="progress"><div class="progress-bar bg-warning" style="width:{{ $dPct }}%">{{ $dPct }}%</div></div>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <strong>Total Gross</strong><strong class="text-success">{{ number_format($totalRev) }} ៛</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Commission Earned</span><span class="text-info font-weight-bold">{{ number_format($commission) }} ៛</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Driver Tips</span><span class="text-success font-weight-bold">{{ number_format($tips) }} ៛</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-info">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-credit-card mr-2"></i>Payment Methods (Rides)</h3></div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-light"><tr><th>Method</th><th class="text-center">Rides</th><th class="text-right">Revenue</th><th>Share</th></tr></thead>
            <tbody>
                @php $pmTotal = $paymentMethods->sum('rev') ?: 1; @endphp
                @foreach($paymentMethods as $pm)
                @php $pmPct = round(($pm->rev/$pmTotal)*100,1); @endphp
                <tr>
                    <td><strong>{{ ucfirst($pm->payment_method ?? '—') }}</strong></td>
                    <td class="text-center">{{ $pm->c }}</td>
                    <td class="text-right font-weight-bold">{{ number_format($pm->rev) }} ៛</td>
                    <td style="width:200px;">
                        <div class="progress" style="height:14px;">
                            <div class="progress-bar bg-info" style="width:{{ $pmPct }}%">{{ $pmPct }}%</div>
                        </div>
                    </td>
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
    var dates=@json($trendDates), rData=@json($dailyRevenue), dData=@json($dailyDelivery);
    var labels=dates.map(d=>{var dt=new Date(d);return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short'});});
    var ride=dates.map(d=>rData[d]?rData[d].ride_rev:0);
    var del=dates.map(d=>dData[d]?dData[d].del_rev:0);
    new Chart(document.getElementById('finChart'),{
        type:'bar',
        data:{labels,datasets:[
            {label:'Ride Revenue',data:ride,backgroundColor:'rgba(23,162,184,.7)',borderRadius:3},
            {label:'Delivery Revenue',data:del,backgroundColor:'rgba(255,193,7,.7)',borderRadius:3},
        ]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true}}}
    });
})();
</script>
@endpush
