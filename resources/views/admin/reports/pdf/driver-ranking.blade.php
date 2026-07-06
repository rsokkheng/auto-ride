@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Driver Ranking Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ $rows->count() }}</div><div class="lbl">Total Drivers</div></div>
    <div class="summary-card"><div class="val">{{ $rows->where('available',1)->count() }}</div><div class="lbl">Online Now</div></div>
    <div class="summary-card"><div class="val">{{ number_format($rows->sum(fn($r)=>$r->rides+$r->deliveries)) }}</div><div class="lbl">Total Jobs</div></div>
    <div class="summary-card"><div class="val">{{ number_format($rows->sum(fn($r)=>$r->ride_rev+$r->del_rev)) }} ៛</div><div class="lbl">Gross Revenue</div></div>
</div>

<div class="section-title">Driver Ranking</div>
<table>
    <thead>
        <tr><th>Rank</th><th>Driver</th><th>Phone</th><th>Status</th><th class="text-center">Rides</th><th class="text-center">Deliveries</th><th class="text-center">Total</th><th class="text-right">Ride Rev</th><th class="text-right">Del Rev</th><th class="text-right">Total Rev</th><th class="text-center">Rating</th></tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $d)
        @php $totalJobs=$d->rides+$d->deliveries; $totalRev=$d->ride_rev+$d->del_rev; @endphp
        <tr>
            <td class="text-center"><strong>{{ $i+1 }}</strong></td>
            <td>{{ $d->name }}</td>
            <td>{{ $d->phone }}</td>
            <td><span class="badge badge-{{ $d->available?'success':'secondary' }}">{{ $d->available?'Online':'Offline' }}</span></td>
            <td class="text-center">{{ $d->rides }}</td>
            <td class="text-center">{{ $d->deliveries }}</td>
            <td class="text-center"><strong>{{ $totalJobs }}</strong></td>
            <td class="text-right">{{ number_format($d->ride_rev) }}</td>
            <td class="text-right">{{ number_format($d->del_rev) }}</td>
            <td class="text-right"><strong>{{ number_format($totalRev) }}</strong></td>
            <td class="text-center">{{ $d->rating ? number_format($d->rating,2) : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
