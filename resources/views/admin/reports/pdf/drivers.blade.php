@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Driver Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ $rows->count() }}</div><div class="lbl">Total Drivers</div></div>
    <div class="summary-card"><div class="val">{{ $rows->where('available',1)->count() }}</div><div class="lbl">Online</div></div>
    <div class="summary-card"><div class="val">{{ number_format($rows->sum(fn($r)=>$r->rides+$r->deliveries)) }}</div><div class="lbl">Total Jobs</div></div>
    <div class="summary-card"><div class="val">{{ number_format($rows->sum('revenue')) }} ៛</div><div class="lbl">Gross Revenue</div></div>
</div>

<div class="section-title">Driver Activity</div>
<table>
    <thead>
        <tr><th>Rank</th><th>Driver</th><th>Phone</th><th>Status</th><th>Rides</th><th>Deliveries</th><th>Total</th><th class="text-right">Revenue (KHR)</th><th class="text-center">Rating</th></tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $d)
        <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td>{{ $d->name }}</td>
            <td>{{ $d->phone }}</td>
            <td><span class="badge badge-{{ $d->available?'success':'secondary' }}">{{ $d->available?'Online':'Offline' }}</span></td>
            <td class="text-center">{{ $d->rides }}</td>
            <td class="text-center">{{ $d->deliveries }}</td>
            <td class="text-center"><strong>{{ $d->rides+$d->deliveries }}</strong></td>
            <td class="text-right">{{ number_format($d->revenue) }}</td>
            <td class="text-center">{{ $d->rating ? number_format($d->rating,2) : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
