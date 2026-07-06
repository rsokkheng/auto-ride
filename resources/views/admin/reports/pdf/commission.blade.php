@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Commission Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ number_format($total) }} ៛</div><div class="lbl">Total Commission</div></div>
    <div class="summary-card"><div class="val">{{ $rows->count() }}</div><div class="lbl">Transactions</div></div>
    <div class="summary-card"><div class="val">{{ $byDriver->count() }}</div><div class="lbl">Drivers</div></div>
    <div class="summary-card"><div class="val">{{ $rows->count()>0?number_format($total/$rows->count()):0 }} ៛</div><div class="lbl">Avg Per Trip</div></div>
</div>

<div class="section-title">Commission by Driver</div>
<table>
    <thead><tr><th>Driver</th><th class="text-center">Trips</th><th class="text-right">Commission (KHR)</th></tr></thead>
    <tbody>
        @foreach($byDriver as $d)
        <tr>
            <td>{{ $d->name }}</td>
            <td class="text-center">{{ $d->trips }}</td>
            <td class="text-right"><strong>{{ number_format($d->commission) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">Commission Details</div>
<table>
    <thead><tr><th>#</th><th>Driver</th><th>Phone</th><th class="text-right">Amount (KHR)</th><th class="text-right">Balance After</th><th>Note</th><th>Date</th></tr></thead>
    <tbody>
        @foreach($rows->take(50) as $r)
        <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->name }}</td>
            <td>{{ $r->phone }}</td>
            <td class="text-right">{{ number_format($r->amount) }}</td>
            <td class="text-right">{{ number_format($r->balance_after) }}</td>
            <td style="max-width:120px;">{{ Str::limit($r->note,30) }}</td>
            <td>{{ \Carbon\Carbon::parse($r->created_at)->format('d M H:i') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
