@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Customer Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ $rows->count() }}</div><div class="lbl">Total Customers</div></div>
    <div class="summary-card"><div class="val">{{ $rows->where('rides','>',0)->count() }}</div><div class="lbl">Active</div></div>
    <div class="summary-card"><div class="val">{{ number_format($rows->sum('rides')) }}</div><div class="lbl">Total Rides</div></div>
    <div class="summary-card"><div class="val">{{ number_format($rows->sum('spent')) }} ៛</div><div class="lbl">Total Spent</div></div>
</div>

<div class="section-title">Top Customers</div>
<table>
    <thead>
        <tr><th>#</th><th>Name</th><th>Phone</th><th>Joined</th><th class="text-center">Rides</th><th class="text-right">Spent (KHR)</th><th class="text-center">Avg Rating Given</th></tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $c)
        <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td>{{ $c->name }}</td>
            <td>{{ $c->phone }}</td>
            <td>{{ \Carbon\Carbon::parse($c->joined)->format('d M Y') }}</td>
            <td class="text-center">{{ $c->rides }}</td>
            <td class="text-right">{{ number_format($c->spent ?? 0) }}</td>
            <td class="text-center">{{ $c->avg_rating ? round($c->avg_rating,1) : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
