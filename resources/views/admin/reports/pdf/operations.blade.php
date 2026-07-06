@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Operations Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ number_format($summary['totalDeliveries']) }}</div><div class="lbl">Deliveries</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['totalRides']) }}</div><div class="lbl">Rides</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['deliveryRev']+$summary['rideRev']) }} ៛</div><div class="lbl">Total Revenue</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['commission']) }} ៛</div><div class="lbl">Commission</div></div>
</div>

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ $summary['activeDrivers'] }}</div><div class="lbl">Active Drivers</div></div>
    <div class="summary-card"><div class="val" style="color:{{ $summary['unassigned']>0?'#dc2626':'#16a34a' }}">{{ $summary['unassigned'] }}</div><div class="lbl">Unassigned Orders</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['rideRev']) }} ៛</div><div class="lbl">Ride Revenue</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['deliveryRev']) }} ៛</div><div class="lbl">Delivery Revenue</div></div>
</div>

<div class="section-title">Delivery Status Breakdown</div>
<table>
    <thead><tr><th>Status</th><th class="text-center">Count</th><th class="text-right">Revenue (KHR)</th></tr></thead>
    <tbody>
        @php $sc=['completed'=>'success','delivered'=>'success','in_transit'=>'info','picked_up'=>'info','assigned'=>'warning','created'=>'secondary','cancelled'=>'danger']; @endphp
        @foreach($deliveries as $row)
        <tr>
            <td><span class="badge badge-{{ $sc[$row->status]??'secondary' }}">{{ ucfirst(str_replace('_',' ',$row->status)) }}</span></td>
            <td class="text-center">{{ $row->c }}</td>
            <td class="text-right">{{ number_format($row->rev ?? 0) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">Ride Status Breakdown</div>
<table>
    <thead><tr><th>Status</th><th class="text-center">Count</th><th class="text-right">Revenue (KHR)</th></tr></thead>
    <tbody>
        @foreach($rides as $row)
        <tr>
            <td><span class="badge badge-{{ $row->status==='completed'?'success':($row->status==='cancelled'?'danger':'warning') }}">{{ ucfirst($row->status) }}</span></td>
            <td class="text-center">{{ $row->c }}</td>
            <td class="text-right">{{ number_format($row->rev ?? 0) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
