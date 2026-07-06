@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Performance Report'; @endphp
@section('content')

@php
$rTotal  = $rideKpi->total ?? 0;
$rDone   = $rideKpi->completed ?? 0;
$rCancel = $rideKpi->cancelled ?? 0;
$dTotal  = $delivKpi->total ?? 0;
$dDone   = $delivKpi->completed ?? 0;
$dCancel = $delivKpi->cancelled ?? 0;
@endphp

<div class="section-title">KPI Summary</div>
<table>
    <thead><tr><th>Metric</th><th class="text-center">Rides</th><th class="text-center">Deliveries</th></tr></thead>
    <tbody>
        <tr><td>Total</td><td class="text-center">{{ $rTotal }}</td><td class="text-center">{{ $dTotal }}</td></tr>
        <tr><td>Completed</td><td class="text-center">{{ $rDone }}</td><td class="text-center">{{ $dDone }}</td></tr>
        <tr><td>Cancelled</td><td class="text-center">{{ $rCancel }}</td><td class="text-center">{{ $dCancel }}</td></tr>
        <tr>
            <td>Completion Rate</td>
            <td class="text-center">{{ $rTotal>0?round($rDone/$rTotal*100,1):0 }}%</td>
            <td class="text-center">{{ $dTotal>0?round($dDone/$dTotal*100,1):0 }}%</td>
        </tr>
        <tr>
            <td>Cancellation Rate</td>
            <td class="text-center">{{ $rTotal>0?round($rCancel/$rTotal*100,1):0 }}%</td>
            <td class="text-center">{{ $dTotal>0?round($dCancel/$dTotal*100,1):0 }}%</td>
        </tr>
        <tr>
            <td>Avg Duration (min)</td>
            <td class="text-center">{{ $rideKpi->avg_min ? round($rideKpi->avg_min) : '—' }}</td>
            <td class="text-center">{{ $delivKpi->avg_min ? round($delivKpi->avg_min) : '—' }}</td>
        </tr>
        <tr>
            <td>Avg Rating</td>
            <td class="text-center">{{ $rideKpi->avg_rating ? round($rideKpi->avg_rating,2) : '—' }}</td>
            <td class="text-center">{{ $delivKpi->avg_rating ? round($delivKpi->avg_rating,2) : '—' }}</td>
        </tr>
    </tbody>
</table>

@if($cancelReasons->isNotEmpty())
<div class="section-title">Top Cancellation Reasons (Rides)</div>
<table>
    <thead><tr><th>Reason</th><th class="text-center">Count</th></tr></thead>
    <tbody>
        @foreach($cancelReasons as $r)
        <tr><td>{{ $r->cancellation_reason ?: '(no reason)' }}</td><td class="text-center">{{ $r->c }}</td></tr>
        @endforeach
    </tbody>
</table>
@endif
@endsection
