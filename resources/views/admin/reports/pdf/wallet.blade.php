@extends('admin.reports.pdf.layout')
@php $reportTitle = 'Wallet Report'; @endphp
@section('content')

<div class="section-title">Transaction Type Summary</div>
<table>
    <thead><tr><th>Type</th><th>Direction</th><th class="text-center">Count</th><th class="text-right">Total (KHR)</th></tr></thead>
    <tbody>
        @foreach($byType as $row)
        <tr>
            <td>{{ str_replace('_',' ',ucfirst($row->type)) }}</td>
            <td><span class="badge badge-{{ $row->direction==='credit'?'success':'danger' }}">{{ $row->direction }}</span></td>
            <td class="text-center">{{ $row->c }}</td>
            <td class="text-right">{{ number_format($row->total) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">Transaction Details</div>
<table>
    <thead><tr><th>#</th><th>User</th><th>Role</th><th>Type</th><th>Dir</th><th class="text-right">Amount</th><th class="text-right">Balance After</th><th>Date</th></tr></thead>
    <tbody>
        @foreach($rows->take(50) as $tx)
        <tr>
            <td>{{ $tx->id }}</td>
            <td>{{ $tx->name }}</td>
            <td>{{ $tx->role }}</td>
            <td>{{ str_replace('_',' ',$tx->type) }}</td>
            <td><span class="badge badge-{{ $tx->direction==='credit'?'success':'danger' }}">{{ $tx->direction }}</span></td>
            <td class="text-right">{{ number_format($tx->amount) }}</td>
            <td class="text-right">{{ number_format($tx->balance_after) }}</td>
            <td>{{ \Carbon\Carbon::parse($tx->created_at)->format('d M H:i') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@if($rows->count() > 50)<p style="font-size:9px;color:#6b7280;">* Showing first 50 of {{ $rows->count() }} records. Export to Excel for full data.</p>@endif
@endsection
