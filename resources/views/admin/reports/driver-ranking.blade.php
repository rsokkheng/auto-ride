@extends('admin.layout')
@section('title', 'Driver Ranking')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-trophy mr-2 text-warning"></i>Driver Ranking Report</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li><li class="breadcrumb-item">Reports</li><li class="breadcrumb-item active">Driver Ranking</li></ol></div>
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
            <span class="ml-4 font-weight-bold">Sort by:</span>
            @foreach(['total'=>'Total Jobs','rides'=>'Rides','delivery'=>'Deliveries','revenue'=>'Revenue','rating'=>'Rating'] as $k=>$l)
            <a href="{{ request()->fullUrlWithQuery(['sort'=>$k]) }}" class="btn btn-sm {{ $sortBy==$k?'btn-dark':'btn-outline-dark' }}">{{ $l }}</a>
            @endforeach
            @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.driver-ranking'])
        </form>
    </div>
</div>

{{-- Summary Stats --}}
<div class="row mb-4">
    <div class="col-lg-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-warning"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Drivers</span>
                <span class="info-box-number">{{ $drivers->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Active Drivers</span>
                <span class="info-box-number">{{ $drivers->where('available',1)->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-route"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Jobs Done</span>
                <span class="info-box-number">{{ number_format($drivers->sum(fn($d)=>$d->rides+$d->deliveries)) }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-danger"><i class="fas fa-coins"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Gross Revenue</span>
                <span class="info-box-number">{{ number_format($drivers->sum(fn($d)=>$d->ride_revenue+$d->delivery_revenue)) }} ៛</span>
            </div>
        </div>
    </div>
</div>

{{-- Top 3 Podium --}}
@php $top3 = $drivers->take(3); @endphp
@if($top3->count() >= 1)
<div class="row justify-content-center mb-4">
    {{-- 2nd place --}}
    @if($top3->count() >= 2)
    <div class="col-lg-3 col-md-4 text-center" style="margin-top:30px;">
        <div class="card shadow" style="border-top:4px solid #adb5bd;">
            <div class="card-body py-3">
                <div style="font-size:2rem;background:#adb5bd;color:#fff;border-radius:50%;width:56px;height:56px;line-height:56px;margin:0 auto 8px;">2</div>
                <h5 class="mb-0">{{ $top3->get(1)->name }}</h5>
                <small class="text-muted">{{ $top3->get(1)->phone }}</small>
                <div class="mt-2"><span class="badge badge-info">{{ $top3->get(1)->rides }} rides</span> <span class="badge badge-primary">{{ $top3->get(1)->deliveries }} deliveries</span></div>
                <div class="mt-1 font-weight-bold">{{ number_format(($top3->get(1)->ride_revenue+$top3->get(1)->delivery_revenue)) }} ៛</div>
            </div>
        </div>
    </div>
    @endif
    {{-- 1st place --}}
    <div class="col-lg-3 col-md-4 text-center">
        <div class="card shadow-lg" style="border-top:5px solid #ffc107;">
            <div class="card-body py-3">
                <div style="font-size:2.5rem;background:#ffc107;color:#fff;border-radius:50%;width:72px;height:72px;line-height:72px;margin:0 auto 8px;"><i class="fas fa-crown"></i></div>
                <h4 class="mb-0">{{ $top3->get(0)->name }}</h4>
                <small class="text-muted">{{ $top3->get(0)->phone }}</small>
                <div class="mt-2"><span class="badge badge-info">{{ $top3->get(0)->rides }} rides</span> <span class="badge badge-primary">{{ $top3->get(0)->deliveries }} deliveries</span></div>
                <div class="mt-1 font-weight-bold text-warning" style="font-size:1.1rem;">{{ number_format(($top3->get(0)->ride_revenue+$top3->get(0)->delivery_revenue)) }} ៛</div>
                @if($top3->get(0)->rating)<div class="text-warning mt-1"><i class="fas fa-star"></i> {{ number_format($top3->get(0)->rating,2) }}</div>@endif
            </div>
        </div>
    </div>
    {{-- 3rd place --}}
    @if($top3->count() >= 3)
    <div class="col-lg-3 col-md-4 text-center" style="margin-top:40px;">
        <div class="card shadow" style="border-top:4px solid #cd7f32;">
            <div class="card-body py-3">
                <div style="font-size:2rem;background:#cd7f32;color:#fff;border-radius:50%;width:56px;height:56px;line-height:56px;margin:0 auto 8px;">3</div>
                <h5 class="mb-0">{{ $top3->get(2)->name }}</h5>
                <small class="text-muted">{{ $top3->get(2)->phone }}</small>
                <div class="mt-2"><span class="badge badge-info">{{ $top3->get(2)->rides }} rides</span> <span class="badge badge-primary">{{ $top3->get(2)->deliveries }} deliveries</span></div>
                <div class="mt-1 font-weight-bold">{{ number_format(($top3->get(2)->ride_revenue+$top3->get(2)->delivery_revenue)) }} ៛</div>
            </div>
        </div>
    </div>
    @endif
</div>
@endif

{{-- Full Ranking Table --}}
<div class="card card-outline card-warning">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-list-ol mr-2"></i>Full Driver Ranking</h3></div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>Rank</th><th>Driver</th><th>Phone</th><th class="text-center">Status</th>
                    <th class="text-center">Rides</th><th class="text-center">Deliveries</th><th class="text-center">Total</th>
                    <th class="text-right">Ride Rev</th><th class="text-right">Del Rev</th><th class="text-right">Total Rev</th>
                    <th class="text-center">Rating</th><th class="text-right">Wallet</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drivers as $i => $d)
                @php $totalJobs = $d->rides + $d->deliveries; $totalRev = $d->ride_revenue + $d->delivery_revenue; @endphp
                <tr class="{{ $i<3?'table-'.['warning','light','light'][$i]:'' }}">
                    <td>
                        @if($i==0)<span class="badge badge-warning"><i class="fas fa-crown"></i> 1</span>
                        @elseif($i==1)<span class="badge badge-secondary">2</span>
                        @elseif($i==2)<span class="badge" style="background:#cd7f32;color:#fff;">3</span>
                        @else <span class="text-muted">{{ $i+1 }}</span>@endif
                    </td>
                    <td><strong>{{ $d->name }}</strong></td>
                    <td><small>{{ $d->phone }}</small></td>
                    <td class="text-center">
                        @if($d->available)<span class="badge badge-success">Online</span>
                        @else<span class="badge badge-secondary">Offline</span>@endif
                    </td>
                    <td class="text-center"><span class="badge badge-info">{{ $d->rides }}</span></td>
                    <td class="text-center"><span class="badge badge-primary">{{ $d->deliveries }}</span></td>
                    <td class="text-center font-weight-bold">{{ $totalJobs }}</td>
                    <td class="text-right">{{ number_format($d->ride_revenue) }}</td>
                    <td class="text-right">{{ number_format($d->delivery_revenue) }}</td>
                    <td class="text-right font-weight-bold text-success">{{ number_format($totalRev) }} ៛</td>
                    <td class="text-center">
                        @if($d->rating)<i class="fas fa-star text-warning"></i> {{ number_format($d->rating,2) }}
                        @else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="text-right">{{ number_format($d->wallet_balance) }}</td>
                </tr>
                @empty
                <tr><td colspan="12" class="text-center py-4 text-muted">No driver data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>
@endsection
