@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Analytics Report (' . ucfirst($view) . ')'; @endphp
@section('content')

<div class="section-title">{{ ucfirst($view) }} Analytics Summary</div>
<table>
    <thead>
        <tr><th>Period</th><th class="text-center">Rides Total</th><th class="text-center">Rides Done</th><th class="text-right">Ride Rev</th><th class="text-center">Del Total</th><th class="text-center">Del Done</th><th class="text-right">Del Rev</th><th class="text-right">Combined Rev</th></tr>
    </thead>
    <tbody>
        @foreach($labels as $label)
        @php
        $r = $rides[$label] ?? null;
        $d = $deliveries[$label] ?? null;
        $combined = ($r->revenue ?? 0) + ($d->revenue ?? 0);
        @endphp
        <tr>
            <td>{{ $label }}</td>
            <td class="text-center">{{ $r->total ?? 0 }}</td>
            <td class="text-center">{{ $r->completed ?? 0 }}</td>
            <td class="text-right">{{ number_format($r->revenue ?? 0) }}</td>
            <td class="text-center">{{ $d->total ?? 0 }}</td>
            <td class="text-center">{{ $d->completed ?? 0 }}</td>
            <td class="text-right">{{ number_format($d->revenue ?? 0) }}</td>
            <td class="text-right"><strong>{{ number_format($combined) }}</strong></td>
        </tr>
        @endforeach
        <tr style="background:#f0f4ff;font-weight:bold;">
            <td>TOTAL</td>
            <td class="text-center">{{ $rides->sum('total') }}</td>
            <td class="text-center">{{ $rides->sum('completed') }}</td>
            <td class="text-right">{{ number_format($rides->sum('revenue')) }}</td>
            <td class="text-center">{{ $deliveries->sum('total') }}</td>
            <td class="text-center">{{ $deliveries->sum('completed') }}</td>
            <td class="text-right">{{ number_format($deliveries->sum('revenue')) }}</td>
            <td class="text-right">{{ number_format($rides->sum('revenue') + $deliveries->sum('revenue')) }}</td>
        </tr>
    </tbody>
</table>
@endsection
