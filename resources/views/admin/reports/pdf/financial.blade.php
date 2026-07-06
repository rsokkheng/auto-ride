@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Financial Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ number_format($summary['rideRev']) }} ៛</div><div class="lbl">Ride Revenue</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['deliveryRev']) }} ៛</div><div class="lbl">Delivery Revenue</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['rideRev']+$summary['deliveryRev']) }} ៛</div><div class="lbl">Total Revenue</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['commission']) }} ៛</div><div class="lbl">Commission</div></div>
</div>

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ number_format($summary['topups']) }} ៛</div><div class="lbl">Top-ups Approved</div></div>
    <div class="summary-card"><div class="val">{{ number_format($summary['withdrawals']) }} ៛</div><div class="lbl">Withdrawals Paid</div></div>
    <div class="summary-card"><div class="val">—</div><div class="lbl">&nbsp;</div></div>
    <div class="summary-card"><div class="val">—</div><div class="lbl">&nbsp;</div></div>
</div>

<div class="section-title">Daily Revenue Breakdown</div>
<table>
    <thead>
        <tr><th>Date</th><th class="text-center">Rides</th><th class="text-right">Ride Rev (KHR)</th><th class="text-center">Deliveries</th><th class="text-right">Del Rev (KHR)</th><th class="text-right">Total (KHR)</th></tr>
    </thead>
    <tbody>
        @foreach($allDates as $date)
        @php
        $rr = $daily[$date]->ride_rev ?? 0;
        $dr = $dailyDel[$date]->del_rev ?? 0;
        @endphp
        <tr>
            <td>{{ $date }}</td>
            <td class="text-center">{{ $daily[$date]->rides ?? 0 }}</td>
            <td class="text-right">{{ number_format($rr) }}</td>
            <td class="text-center">{{ $dailyDel[$date]->deliveries ?? 0 }}</td>
            <td class="text-right">{{ number_format($dr) }}</td>
            <td class="text-right"><strong>{{ number_format($rr+$dr) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
