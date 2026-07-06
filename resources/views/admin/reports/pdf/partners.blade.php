@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Partner Report'; @endphp
@section('content')

<div class="section-title">Partner Performance</div>
<table>
    <thead>
        <tr><th>#</th><th>Partner</th><th>Phone</th><th>Orders</th><th>Done</th><th>Cancelled</th><th>Express</th><th>Success %</th><th class="text-right">Revenue (KHR)</th><th class="text-right">Wallet (KHR)</th></tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $p)
        @php $rate=$p->orders>0?round(($p->done/$p->orders)*100):0; @endphp
        <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td><strong>{{ $p->name }}</strong></td>
            <td>{{ $p->phone }}</td>
            <td class="text-center">{{ $p->orders }}</td>
            <td class="text-center">{{ $p->done }}</td>
            <td class="text-center">{{ $p->cancelled }}</td>
            <td class="text-center">{{ $p->express }}</td>
            <td class="text-center"><span class="badge badge-{{ $rate>=80?'success':($rate>=50?'warning':'danger') }}">{{ $rate }}%</span></td>
            <td class="text-right">{{ number_format($p->revenue ?? 0) }}</td>
            <td class="text-right">{{ number_format($p->wallet_balance) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
