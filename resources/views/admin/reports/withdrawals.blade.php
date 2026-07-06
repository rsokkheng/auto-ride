@extends('admin.layout')
@section('title', 'Withdrawal Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-money-bill-wave mr-2 text-danger"></i>Withdrawal Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Withdrawals</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

<div class="card card-outline card-danger mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'7 Days',30=>'30 Days',60=>'60 Days',90=>'90 Days'] as $d=>$l)
            <a href="{{ request()->fullUrlWithQuery(['period'=>$d]) }}" class="btn btn-sm {{ $period==$d?'btn-danger':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
            @include('admin.reports.partials.scope-filter')
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.withdrawals'])
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $totals->total ?? 0 }}</h3><p>Total Requests</p></div>
            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="small-box-footer">In period</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $totals->pending ?? 0 }}</h3><p>Pending</p></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
            <div class="small-box-footer">Awaiting review: {{ number_format($totals->total_pending ?? 0) }} ៛</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ $totals->approved ?? 0 }}</h3><p>Approved</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <div class="small-box-footer">Paid: {{ number_format($totals->total_paid ?? 0) }} ៛</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ $totals->rejected ?? 0 }}</h3><p>Rejected</p></div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <div class="small-box-footer">
                @php $tot=$totals->total??0; @endphp
                Approve rate: {{ $tot>0?round(($totals->approved/$tot)*100):0 }}%
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-danger">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Daily Withdrawal Trend</h3></div>
            <div class="card-body"><canvas id="wdChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">By Payment Method</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Method</th><th class="text-center">Count</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($byMethod as $m)
                        <tr>
                            <td><strong>{{ ucfirst($m->payment_method ?? '—') }}</strong></td>
                            <td class="text-center">{{ $m->c }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($m->total) }} ៛</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-danger">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-table mr-2"></i>Withdrawal Requests</h3>
        <div class="card-tools">
            <a href="{{ route('admin.withdrawals') }}" class="btn btn-sm btn-outline-danger">Manage →</a>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-dark">
                <tr><th>#</th><th>Driver</th><th>Phone</th><th class="text-right">Amount</th><th>Method</th><th>Account</th><th>Bank</th><th>Status</th><th>Note</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $w)
                <tr>
                    <td>{{ $w->id }}</td>
                    <td><strong>{{ $w->name }}</strong></td>
                    <td>{{ $w->phone }}</td>
                    <td class="text-right font-weight-bold">{{ number_format($w->amount_khr) }} ៛</td>
                    <td>{{ $w->payment_method }}</td>
                    <td><small>{{ $w->account_number }}</small></td>
                    <td><small>{{ $w->bank_name }}</small></td>
                    <td>
                        <span class="badge badge-{{ $w->status==='approved'?'success':($w->status==='rejected'?'danger':'warning') }}">
                            {{ ucfirst($w->status) }}
                        </span>
                    </td>
                    <td><small class="text-muted">{{ Str::limit($w->admin_note,20) }}</small></td>
                    <td><small>{{ \Carbon\Carbon::parse($w->created_at)->format('d M H:i') }}</small></td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center py-4 text-muted">No withdrawal requests in this period</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($withdrawals->hasPages())
    <div class="card-footer">{{ $withdrawals->links() }}</div>
    @endif
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
    new Chart(document.getElementById('wdChart'),{
        type:'bar',
        data:{labels,datasets:[
            {label:'Requests',data:count,backgroundColor:'rgba(220,53,69,.6)',borderRadius:3,yAxisID:'y'},
            {label:'Amount (KHR)',data:amounts,type:'line',borderColor:'rgba(255,193,7,1)',backgroundColor:'transparent',yAxisID:'y1',tension:0.4},
        ]},
        options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}}}
    });
})();
</script>
@endpush
