@extends('admin.layout')
@section('title', 'Analytics Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-chart-line mr-2 text-primary"></i>Analytics Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Analytics</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

{{-- View Switcher --}}
<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="font-weight-bold mr-2">View:</span>
            @foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','yearly'=>'Yearly'] as $k=>$l)
            <a href="{{ request()->fullUrlWithQuery(['view'=>$k]) }}" class="btn btn-sm {{ $view==$k?'btn-primary':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
            @include('admin.reports.partials.scope-filter')
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.analytics', 'extraParams' => ['view' => $view]])
        </div>
    </div>
</div>

{{-- All-Time Summary --}}
<div class="row">
    @php $allTimeItems = [
        ['label'=>'All-Time Rides','value'=>number_format($allTime['rides']),'icon'=>'fa-car','color'=>'info'],
        ['label'=>'All-Time Deliveries','value'=>number_format($allTime['deliveries']),'icon'=>'fa-box','color'=>'warning'],
        ['label'=>'Total Users','value'=>number_format($allTime['users']),'icon'=>'fa-users','color'=>'success'],
        ['label'=>'Total Drivers','value'=>number_format($allTime['drivers']),'icon'=>'fa-id-badge','color'=>'primary'],
        ['label'=>'Total Partners','value'=>number_format($allTime['partners']),'icon'=>'fa-handshake','color'=>'secondary'],
        ['label'=>'All-Time Revenue','value'=>number_format($allTime['revenue']).' ៛','icon'=>'fa-coins','color'=>'success'],
        ['label'=>'All-Time Commission','value'=>number_format($allTime['commission']).' ៛','icon'=>'fa-percentage','color'=>'danger'],
    ]; @endphp
    @foreach($allTimeItems as $item)
    <div class="col-lg-2 col-md-4 col-6">
        <div class="info-box shadow-sm mb-3">
            <span class="info-box-icon bg-{{ $item['color'] }}"><i class="fas {{ $item['icon'] }}"></i></span>
            <div class="info-box-content">
                <span class="info-box-text" style="font-size:.7rem;">{{ $item['label'] }}</span>
                <span class="info-box-number" style="font-size:.95rem;">{{ $item['value'] }}</span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Rides + Deliveries Trend --}}
<div class="row">
    <div class="col-lg-6">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-car mr-2"></i>Ride Trend ({{ ucfirst($view) }})</h3></div>
            <div class="card-body"><canvas id="rideTrendChart" height="160"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-box mr-2"></i>Delivery Trend ({{ ucfirst($view) }})</h3></div>
            <div class="card-body"><canvas id="deliveryTrendChart" height="160"></canvas></div>
        </div>
    </div>
</div>

{{-- Revenue Trend --}}
<div class="card card-outline card-success">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-coins mr-2"></i>Revenue + Commission Trend ({{ ucfirst($view) }})</h3></div>
    <div class="card-body"><canvas id="revTrendChart" height="100"></canvas></div>
</div>

{{-- User Growth --}}
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-users mr-2"></i>User Growth ({{ ucfirst($view) }})</h3></div>
    <div class="card-body"><canvas id="userGrowthChart" height="100"></canvas></div>
</div>

{{-- Data Table --}}
<div class="row">
    <div class="col-lg-6">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Ride Data Table</h3></div>
            <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Period</th><th class="text-center">Total</th><th class="text-center">Done</th><th class="text-center">Cancel</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody>
                        @foreach($rideTrend as $r)
                        <tr>
                            <td>{{ $r->label }}</td>
                            <td class="text-center">{{ $r->total }}</td>
                            <td class="text-center text-success">{{ $r->completed }}</td>
                            <td class="text-center text-danger">{{ $r->cancelled }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($r->revenue) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title">Delivery Data Table</h3></div>
            <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Period</th><th class="text-center">Total</th><th class="text-center">Done</th><th class="text-center">Cancel</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody>
                        @foreach($deliveryTrend as $d)
                        <tr>
                            <td>{{ $d->label }}</td>
                            <td class="text-center">{{ $d->total }}</td>
                            <td class="text-center text-success">{{ $d->completed }}</td>
                            <td class="text-center text-danger">{{ $d->cancelled }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($d->revenue) }}</td>
                        </tr>
                        @endforeach
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
    var rideData     = @json($rideTrend);
    var delivData    = @json($deliveryTrend);
    var commData     = @json($commissionTrend);
    var userData     = @json($userGrowth);

    var rLabels  = rideData.map(r=>r.label);
    var dLabels  = delivData.map(r=>r.label);

    // Rides chart
    new Chart(document.getElementById('rideTrendChart'),{
        type:'bar',
        data:{labels:rLabels,datasets:[
            {label:'Total',data:rideData.map(r=>r.total),backgroundColor:'rgba(23,162,184,.5)',borderRadius:3},
            {label:'Completed',data:rideData.map(r=>r.completed),backgroundColor:'rgba(40,167,69,.6)',borderRadius:3},
            {label:'Cancelled',data:rideData.map(r=>r.cancelled),backgroundColor:'rgba(220,53,69,.5)',borderRadius:3},
        ]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}
    });

    // Delivery chart
    new Chart(document.getElementById('deliveryTrendChart'),{
        type:'bar',
        data:{labels:dLabels,datasets:[
            {label:'Total',data:delivData.map(r=>r.total),backgroundColor:'rgba(255,193,7,.5)',borderRadius:3},
            {label:'Completed',data:delivData.map(r=>r.completed),backgroundColor:'rgba(40,167,69,.6)',borderRadius:3},
            {label:'Cancelled',data:delivData.map(r=>r.cancelled),backgroundColor:'rgba(220,53,69,.5)',borderRadius:3},
        ]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}
    });

    // Revenue + Commission
    var revLabels = rLabels;
    var rideRev   = rideData.map(r=>r.revenue);
    var delRev    = delivData.map(r=>r.revenue||0);
    var commMap   = {};
    commData.forEach(c=>commMap[c.label]=c.total);
    var commVals  = revLabels.map(l=>commMap[l]||0);

    new Chart(document.getElementById('revTrendChart'),{
        type:'bar',
        data:{labels:revLabels,datasets:[
            {label:'Ride Revenue',data:rideRev,backgroundColor:'rgba(23,162,184,.6)',borderRadius:3,stack:'rev'},
            {label:'Delivery Revenue',data:delRev,backgroundColor:'rgba(255,193,7,.6)',borderRadius:3,stack:'rev'},
            {label:'Commission',data:commVals,type:'line',borderColor:'rgba(220,53,69,1)',backgroundColor:'transparent',tension:0.4},
        ]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true}}}
    });

    // User growth by role
    var roles = [...new Set(userData.map(u=>u.role))];
    var periods = [...new Set(userData.map(u=>u.label))];
    var roleColors = {driver:'rgba(40,167,69,.7)',passenger:'rgba(0,123,255,.7)',partner:'rgba(255,193,7,.7)',admin:'rgba(108,117,125,.5)'};
    var datasets = roles.map(role=>({
        label:role.charAt(0).toUpperCase()+role.slice(1),
        data:periods.map(p=>{var r=userData.find(u=>u.label===p&&u.role===role);return r?r.c:0;}),
        backgroundColor:roleColors[role]||'rgba(200,200,200,.5)',
        borderRadius:3,
    }));
    new Chart(document.getElementById('userGrowthChart'),{
        type:'bar',
        data:{labels:periods,datasets},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true,ticks:{precision:0}}}}
    });
})();
</script>
@endpush
