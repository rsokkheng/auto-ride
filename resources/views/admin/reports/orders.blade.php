@extends('admin.layout')
@section('title', 'Order Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-box mr-2 text-info"></i>Order Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Orders</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

{{-- Period Filter --}}
<div class="card card-outline card-info mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'7 Days',30=>'30 Days',60=>'60 Days',90=>'90 Days'] as $d=>$l)
            <a href="{{ request()->fullUrlWithQuery(['period'=>$d]) }}" class="btn btn-sm {{ $period==$d?'btn-info':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
            <span class="text-muted small ml-3">{{ $start->format('d M Y') }} → Today</span>
            @include('admin.reports.partials.scope-filter')
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.orders'])
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format($totals->total ?? 0) }}</h3><p>Total Orders</p></div>
            <div class="icon"><i class="fas fa-box"></i></div>
            <div class="small-box-footer">Deliveries in period</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format($totals->done ?? 0) }}</h3><p>Completed</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <div class="small-box-footer">
                @php $rate = ($totals->total??0)>0 ? round(($totals->done/$totals->total)*100,1) : 0; @endphp
                Success Rate: {{ $rate }}%
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ number_format($totals->total_fee ?? 0) }} <small>៛</small></h3><p>Total Revenue</p></div>
            <div class="icon"><i class="fas fa-coins"></i></div>
            <div class="small-box-footer">COD: {{ number_format($totals->total_cod ?? 0) }} ៛</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ number_format($totals->cancelled ?? 0) }}</h3><p>Cancelled</p></div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <div class="small-box-footer">Avg time: {{ $totals->avg_minutes ? round($totals->avg_minutes).' min' : 'N/A' }}</div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Trend Chart --}}
    <div class="col-lg-8">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Daily Order Trend</h3></div>
            <div class="card-body"><canvas id="orderTrendChart" height="120"></canvas></div>
        </div>
    </div>
    {{-- Status Breakdown --}}
    <div class="col-lg-4">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Status Breakdown</h3></div>
            <div class="card-body p-0">
                @php $colors=['completed'=>'success','delivered'=>'success','in_transit'=>'primary','picked_up'=>'info','assigned'=>'warning','accepted'=>'info','created'=>'secondary','cancelled'=>'danger']; $tot2=$byStatus->sum('c')?:1; @endphp
                <ul class="list-group list-group-flush">
                    @foreach($byStatus as $row)
                    @php $pct=round(($row->c/$tot2)*100,1); @endphp
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge badge-{{ $colors[$row->status]??'secondary' }}">{{ ucfirst(str_replace('_',' ',$row->status)) }}</span>
                            <strong>{{ $row->c }} <small class="text-muted">({{ $pct }}%)</small></strong>
                        </div>
                        <div class="progress" style="height:4px;"><div class="progress-bar bg-{{ $colors[$row->status]??'secondary' }}" style="width:{{ $pct }}%"></div></div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- By Size --}}
    <div class="col-lg-4">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-weight-hanging mr-2"></i>By Package Size</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Size</th><th class="text-right">Count</th><th class="text-right">%</th></tr></thead>
                    <tbody>
                        @php $sTotal=$bySize->sum('c')?:1; @endphp
                        @foreach($bySize as $row)
                        <tr>
                            <td>{{ ucfirst($row->package_size ?? '—') }}</td>
                            <td class="text-right font-weight-bold">{{ $row->c }}</td>
                            <td class="text-right text-muted">{{ round(($row->c/$sTotal)*100,1) }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{-- By Payment --}}
    <div class="col-lg-4">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-money-bill mr-2"></i>Payment By</h3></div>
            <div class="card-body">
                @foreach($byPayment as $row)
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ ucfirst($row->payment_by ?? '—') }}</span>
                    <strong>{{ $row->c }} orders</strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <small class="text-muted">COD amount</small>
                    <small class="font-weight-bold">{{ number_format($row->cod ?? 0) }} ៛</small>
                </div>
                @endforeach
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Partner Orders</span><strong>{{ number_format($totals->partner_orders??0) }}</strong>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span>Regular Orders</span><strong>{{ number_format($totals->regular_orders??0) }}</strong>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span>Express Orders</span><strong class="text-warning">{{ number_format($totals->express_orders??0) }}</strong>
                </div>
            </div>
        </div>
    </div>
    {{-- Recent Orders --}}
    <div class="col-lg-4">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title">Recent 20 Orders</h3></div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                <table class="table table-xs table-hover mb-0">
                    <thead class="thead-light"><tr><th>#</th><th>Recipient</th><th>Status</th><th>Fee</th></tr></thead>
                    <tbody>
                        @foreach($recent as $o)
                        @php $sc=['completed'=>'success','delivered'=>'success','in_transit'=>'primary','picked_up'=>'info','assigned'=>'warning','created'=>'secondary','cancelled'=>'danger']; @endphp
                        <tr>
                            <td><a href="{{ route('admin.deliveries') }}">#{{ $o->id }}</a></td>
                            <td><small>{{ Str::limit($o->recipient_name,15) }}</small></td>
                            <td><span class="badge badge-{{ $sc[$o->status]??'secondary' }}" style="font-size:.65rem;">{{ $o->status }}</span></td>
                            <td><small>{{ number_format($o->fee) }}</small></td>
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
    var dates = @json($trendDates);
    var data  = @json($daily);
    var labels = dates.map(d=>{ var dt=new Date(d); return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short'}); });
    var total  = dates.map(d=>data[d]?data[d].c:0);
    var done   = dates.map(d=>data[d]?data[d].done:0);
    var cancel = dates.map(d=>data[d]?data[d].cancelled:0);
    new Chart(document.getElementById('orderTrendChart'),{
        type:'bar',
        data:{labels,datasets:[
            {label:'Total',data:total,backgroundColor:'rgba(23,162,184,.6)',borderRadius:3},
            {label:'Completed',data:done,backgroundColor:'rgba(40,167,69,.6)',borderRadius:3},
            {label:'Cancelled',data:cancel,backgroundColor:'rgba(220,53,69,.6)',borderRadius:3},
        ]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{stacked:false,grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}
    });
})();
</script>
@endpush
