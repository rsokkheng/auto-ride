@extends('partner.layout')
@section('title', 'Reports')
@section('page-title', 'Reports & Analytics')

@push('styles')
<style>
.metric-card { border-radius: 12px; padding: 20px 24px; }
.chart-bar-wrap { display: flex; align-items: flex-end; gap: 4px; height: 120px; margin-top: 12px; }
.chart-bar { flex: 1; border-radius: 4px 4px 0 0; min-width: 6px; position: relative; cursor: pointer; }
.chart-bar:hover { opacity: .8; }
.chart-bar .tip { display: none; position: absolute; bottom: calc(100% + 4px); left: 50%; transform: translateX(-50%);
    background: #1e293b; color: #fff; font-size: .65rem; padding: 3px 6px; border-radius: 4px; white-space: nowrap; z-index: 9; }
.chart-bar:hover .tip { display: block; }
</style>
@endpush

@section('content')

{{-- Period Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('partner.reports') }}" class="form-inline" style="gap:8px">
            <label class="mb-0 mr-2 small font-weight-bold">Period:</label>
            @foreach([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $label)
                <a href="{{ route('partner.reports', ['days' => $d]) }}"
                   class="btn btn-sm {{ $days == $d ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </form>
    </div>
</div>

{{-- Metrics --}}
<div class="row">
    <div class="col-6 col-md-3 mb-3">
        <div class="metric-card card text-center p-3">
            <div class="text-muted small">Total Orders</div>
            <div class="h3 font-weight-bold mb-0">{{ $total }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="metric-card card text-center p-3">
            <div class="text-muted small">Delivered</div>
            <div class="h3 font-weight-bold text-success mb-0">{{ $delivered }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="metric-card card text-center p-3">
            <div class="text-muted small">Cancelled</div>
            <div class="h3 font-weight-bold text-danger mb-0">{{ $cancelled }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="metric-card card text-center p-3">
            <div class="text-muted small">Success Rate</div>
            <div class="h3 font-weight-bold text-primary mb-0">{{ $successRate }}%</div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Chart --}}
    <div class="col-md-8 mb-3">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-bar text-primary mr-2"></i>Daily Orders (Last {{ $days }} days)</h5></div>
            <div class="card-body">
                @php $maxTotal = $daily->max('total') ?: 1; @endphp
                @if($daily->isEmpty())
                    <div class="text-center text-muted py-5">No data for this period.</div>
                @else
                <div class="chart-bar-wrap">
                    @foreach($daily as $row)
                    @php $pct = round(($row->total / $maxTotal) * 100); @endphp
                    <div class="chart-bar" style="height:{{ max($pct,4) }}%; background:linear-gradient(to top,#3b82f6,#93c5fd);">
                        <div class="tip">{{ \Carbon\Carbon::parse($row->date)->format('d M') }}<br>Total: {{ $row->total }}<br>Done: {{ $row->delivered }}</div>
                    </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">{{ $daily->first()->date ?? '' }}</small>
                    <small class="text-muted">{{ $daily->last()->date ?? '' }}</small>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Avg delivery time + breakdown --}}
    <div class="col-md-4 mb-3">
        <div class="card mb-3 text-center p-3">
            <div class="text-muted small">Avg. Delivery Time</div>
            @if($avgMinutes)
                <div class="h3 font-weight-bold mb-0">{{ $avgMinutes }} min</div>
                <small class="text-muted">≈ {{ round($avgMinutes/60,1) }} hr</small>
            @else
                <div class="text-muted py-2">No completed data</div>
            @endif
        </div>
        <div class="card p-3">
            <div class="text-muted small font-weight-bold mb-2">Breakdown</div>
            @if($total > 0)
            <div class="mb-2">
                <div class="d-flex justify-content-between small"><span>Delivered</span><span>{{ $delivered }} ({{ round($delivered/$total*100,1) }}%)</span></div>
                <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:{{ round($delivered/$total*100) }}%"></div></div>
            </div>
            <div>
                <div class="d-flex justify-content-between small"><span>Cancelled</span><span>{{ $cancelled }} ({{ round($cancelled/$total*100,1) }}%)</span></div>
                <div class="progress" style="height:6px"><div class="progress-bar bg-danger" style="width:{{ round($cancelled/$total*100) }}%"></div></div>
            </div>
            @else
                <div class="text-muted small">No orders in this period.</div>
            @endif
        </div>
    </div>
</div>
@endsection
