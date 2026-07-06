@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Order Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ number_format($totals->total) }}</div><div class="lbl">Total Orders</div></div>
    <div class="summary-card"><div class="val">{{ number_format($totals->done) }}</div><div class="lbl">Completed</div></div>
    <div class="summary-card"><div class="val">{{ number_format($totals->cancelled) }}</div><div class="lbl">Cancelled</div></div>
    <div class="summary-card"><div class="val">{{ number_format($totals->fee) }} ៛</div><div class="lbl">Total Revenue</div></div>
</div>

<div class="section-title">Order Details</div>
<table>
    <thead>
        <tr>
            <th>#</th><th>Recipient</th><th>Phone</th><th>Status</th><th>Service</th><th>Size</th>
            <th class="text-right">Fee (KHR)</th><th class="text-right">COD (KHR)</th><th>Driver</th><th>Partner</th><th>Date</th>
        </tr>
    </thead>
    <tbody>
        @php $sc=['completed'=>'success','delivered'=>'success','in_transit'=>'info','picked_up'=>'info','assigned'=>'warning','created'=>'secondary','cancelled'=>'danger']; @endphp
        @foreach($rows as $r)
        <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->recipient_name }}</td>
            <td>{{ $r->recipient_phone }}</td>
            <td><span class="badge badge-{{ $sc[$r->status]??'secondary' }}">{{ $r->status }}</span></td>
            <td>{{ $r->service_option }}</td>
            <td>{{ $r->package_size }}</td>
            <td class="text-right">{{ number_format($r->fee) }}</td>
            <td class="text-right">{{ $r->package_amount ? number_format($r->package_amount) : '—' }}</td>
            <td>{{ $r->driver ?? '—' }}</td>
            <td>{{ $r->partner ?? '—' }}</td>
            <td>{{ \Carbon\Carbon::parse($r->created_at)->format('d M H:i') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
