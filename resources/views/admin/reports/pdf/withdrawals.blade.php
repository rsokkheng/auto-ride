@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Withdrawal Report'; @endphp
@section('content')

<div class="summary-grid">
    <div class="summary-card"><div class="val">{{ $totals->total }}</div><div class="lbl">Total Requests</div></div>
    <div class="summary-card"><div class="val">{{ $totals->pending }}</div><div class="lbl">Pending</div></div>
    <div class="summary-card"><div class="val">{{ $totals->approved }}</div><div class="lbl">Approved</div></div>
    <div class="summary-card"><div class="val">{{ number_format($totals->paid) }} ៛</div><div class="lbl">Total Paid</div></div>
</div>

<div class="section-title">Withdrawal Requests</div>
<table>
    <thead><tr><th>#</th><th>Driver</th><th>Phone</th><th class="text-right">Amount (KHR)</th><th>Method</th><th>Account</th><th>Bank</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
        @foreach($rows as $w)
        <tr>
            <td>{{ $w->id }}</td>
            <td>{{ $w->name }}</td>
            <td>{{ $w->phone }}</td>
            <td class="text-right">{{ number_format($w->amount_khr) }}</td>
            <td>{{ $w->payment_method }}</td>
            <td>{{ $w->account_number }}</td>
            <td>{{ $w->bank_name }}</td>
            <td><span class="badge badge-{{ $w->status==='approved'?'success':($w->status==='rejected'?'danger':'warning') }}">{{ $w->status }}</span></td>
            <td>{{ \Carbon\Carbon::parse($w->created_at)->format('d M H:i') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
