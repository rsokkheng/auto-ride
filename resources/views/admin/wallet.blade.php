@extends('admin.layout')
@section('title', 'Wallet Transactions')
@section('page-title', 'Wallet Transactions')

@section('content')

<x-admin.page-toolbar subtitle="Every change to a driver's wallet — trip earnings, commissions, salaries, bonuses and withdrawals.">
    <button class="btn btn-secondary" data-toggle="modal" data-target="#creditModal"><i class="fas fa-plus mr-1"></i> Credit Wallet</button>
    <button class="btn btn-primary" data-toggle="modal" data-target="#salaryModal"><i class="fas fa-money-bill-wave mr-1"></i> Pay Salary</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Driver Balances" :value="number_format($stats['balances']) . ' ៛'" icon="fa-wallet" tone="primary" sub="held in all driver wallets" />
    <x-admin.stat-card label="Credited This Month" :value="number_format($stats['credit_month']) . ' ៛'" icon="fa-arrow-down" tone="success"
                       :href="route('admin.wallet', ['direction' => 'credit'])" :active="request('direction') === 'credit'" />
    <x-admin.stat-card label="Debited This Month" :value="number_format($stats['debit_month']) . ' ៛'" icon="fa-arrow-up" tone="danger"
                       :href="route('admin.wallet', ['direction' => 'debit'])" :active="request('direction') === 'debit'" />
    <x-admin.stat-card label="Entries This Month" :value="number_format($stats['count_month'])" icon="fa-list-check" tone="violet" :sub="now()->format('F Y')" />
</x-admin.stat-grid>

@php
    $typeLabels = \App\Models\WalletTransaction::$typeLabels;
    $typeTone = [
        'top_up' => 'success', 'trip_earning' => 'success', 'salary' => 'success', 'bonus' => 'success',
        'platform_commission' => 'warning', 'company_commission' => 'warning', 'rental_fee' => 'warning',
        'withdrawal' => 'danger', 'adjustment' => 'info',
    ];
@endphp
<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.wallet')" placeholder="Search driver name, phone or email" :count="$transactions->total()" noun="entry">
            <x-admin.filter-select name="type" :options="$typeLabels" all="All types" />
            <x-admin.filter-select name="direction" :options="['credit' => 'Money in', 'debit' => 'Money out']" all="In & out" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr><th>Driver</th><th>Type</th><th class="text-right">Amount</th><th class="text-right">Balance After</th><th>Note</th><th>Date</th></tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $tx->user->name }}</div>
                        <div class="text-muted tw:text-xs">#{{ $tx->id }}</div>
                    </td>
                    <td><span class="badge badge-{{ $typeTone[$tx->type] ?? 'secondary' }}">{{ $typeLabels[$tx->type] ?? ucfirst($tx->type) }}</span></td>
                    <td class="text-right" style="font-weight:700;color:{{ $tx->direction === 'credit' ? 'var(--success)' : 'var(--danger)' }};">
                        {{ $tx->direction === 'credit' ? '+' : '−' }}{{ number_format($tx->amount) }} ៛
                    </td>
                    <td class="text-right">{{ number_format($tx->balance_after) }} ៛</td>
                    <td><div class="text-truncate text-muted" style="max-width:260px;" title="{{ $tx->note }}">{{ $tx->note ?? '—' }}</div></td>
                    <td>
                        <div>{{ $tx->created_at->format('d M Y, H:i') }}</div>
                        <div class="text-muted tw:text-xs">{{ $tx->created_at->diffForHumans() }}</div>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-wallet" title="No wallet activity yet" :clear="route('admin.wallet')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$transactions" />
</div>

{{-- Pay salary --}}
<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.wallet.salary') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Pay Salary — Employee Driver</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Driver</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">— Select employee driver —</option>
                        @foreach($employeeDrivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->phone ?? $d->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Amount (KHR ៛)</label>
                        <input type="number" name="amount" class="form-control" min="1000" step="1000" required placeholder="e.g. 500000">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Note</label>
                        <input type="text" name="note" class="form-control" placeholder="Salary {{ now()->format('F Y') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-success"><i class="fas fa-money-bill-wave mr-1"></i> Pay Salary</button>
            </div>
        </form>
    </div>
</div>

{{-- Bonus / adjustment credit --}}
<div class="modal fade" id="creditModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.wallet.credit') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Bonus / Adjustment Credit</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Driver</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">— Select driver —</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}{{ $d->phone ? ' (' . $d->phone . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Amount (KHR)</label>
                        <input type="number" name="amount" class="form-control" min="100" step="100" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Type</label>
                        <select name="type" class="form-control">
                            <option value="bonus">Bonus</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="top_up">Top-up</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Note</label>
                        <input type="text" name="note" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Credit Wallet</button>
            </div>
        </form>
    </div>
</div>
@endsection
