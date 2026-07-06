@extends('admin.layout')
@section('title', 'Wallet Report')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-wallet mr-2 text-info"></i>Wallet Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Wallet</li></ol></div>
</div>
@endsection
@section('content')
<div class="container-fluid">

<div class="card card-outline card-info mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
            <span class="font-weight-bold mr-2">Period:</span>
            @foreach([7=>'7 Days',30=>'30 Days',60=>'60 Days',90=>'90 Days'] as $d=>$l)
            <a href="{{ request()->fullUrlWithQuery(['period'=>$d]) }}" class="btn btn-sm {{ $period==$d?'btn-info':'btn-outline-secondary' }}">{{ $l }}</a>
            @endforeach
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format($totalIn) }} <small style="font-size:.6em;">៛</small></h3><p>Total Credits (In)</p></div>
            <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
            <div class="small-box-footer">Money flowing in</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ number_format($totalOut) }} <small style="font-size:.6em;">៛</small></h3><p>Total Debits (Out)</p></div>
            <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
            <div class="small-box-footer">Money flowing out</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $byType->sum('c') }}</h3><p>Transactions</p></div>
            <div class="icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="small-box-footer">All transaction records</div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $topups->where('status','pending')->count() }}</h3><p>Pending Top-ups</p></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
            <div class="small-box-footer"><a href="{{ route('admin.topups') }}" class="text-white">Review →</a></div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Transaction Type Breakdown --}}
    <div class="col-lg-5">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Transaction Type Breakdown</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Type</th><th>Dir</th><th class="text-center">Count</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($byType as $row)
                        <tr>
                            <td><strong>{{ str_replace('_',' ',ucfirst($row->type)) }}</strong></td>
                            <td>
                                @if($row->direction==='credit')
                                    <span class="badge badge-success"><i class="fas fa-arrow-down"></i> In</span>
                                @else
                                    <span class="badge badge-danger"><i class="fas fa-arrow-up"></i> Out</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $row->c }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($row->total) }} ៛</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Top Wallet Balances --}}
    <div class="col-lg-7">
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-trophy mr-2"></i>Top Wallet Balances</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>#</th><th>User</th><th>Role</th><th>Phone</th><th class="text-right">Balance</th></tr></thead>
                    <tbody>
                        @foreach($topBalances as $i => $u)
                        <tr>
                            <td>{{ $i+1 }}</td>
                            <td><strong>{{ $u->name }}</strong></td>
                            <td><span class="badge badge-secondary">{{ $u->role }}</span></td>
                            <td>{{ $u->phone }}</td>
                            <td class="text-right font-weight-bold text-success">{{ number_format($u->wallet_balance) }} ៛</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Recent Top-ups --}}
    <div class="col-lg-6">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle mr-2"></i>Recent Top-up Requests</h3></div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>#</th><th>User</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($topups as $t)
                        <tr>
                            <td>{{ $t->id }}</td>
                            <td><strong>{{ $t->name }}</strong><br><small>{{ $t->phone }}</small></td>
                            <td class="font-weight-bold">{{ number_format($t->amount) }} ៛</td>
                            <td>{{ $t->method }}</td>
                            <td>
                                <span class="badge badge-{{ $t->status==='approved'?'success':($t->status==='rejected'?'danger':'warning') }}">{{ $t->status }}</span>
                            </td>
                            <td><small>{{ \Carbon\Carbon::parse($t->created_at)->format('d M H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No top-ups in period</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{-- Recent Transactions --}}
    <div class="col-lg-6">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-list mr-2"></i>Recent Transactions</h3></div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>User</th><th>Type</th><th>Dir</th><th class="text-right">Amount</th><th>Date</th></tr></thead>
                    <tbody>
                        @foreach($recent as $tx)
                        <tr>
                            <td><small class="font-weight-bold">{{ $tx->name }}</small><br><small class="text-muted">{{ $tx->role }}</small></td>
                            <td><small>{{ str_replace('_',' ',$tx->type) }}</small></td>
                            <td>
                                @if($tx->direction==='credit')
                                    <span class="text-success"><i class="fas fa-arrow-down"></i></span>
                                @else
                                    <span class="text-danger"><i class="fas fa-arrow-up"></i></span>
                                @endif
                            </td>
                            <td class="text-right {{ $tx->direction==='credit'?'text-success':'text-danger' }} font-weight-bold">
                                {{ $tx->direction==='credit'?'+':'-' }}{{ number_format($tx->amount) }}
                            </td>
                            <td><small>{{ \Carbon\Carbon::parse($tx->created_at)->format('d M H:i') }}</small></td>
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
