@extends('admin.layout')
@section('title', 'Wallet Transactions')
@section('page-title', 'Wallet Transactions')

@section('content')

{{-- Quick actions --}}
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-hand-holding-usd mr-1"></i> Pay Salary / Manual Credit</h3></div>
    <div class="card-body">
        <div class="row">
            {{-- Salary --}}
            <div class="col-md-6">
                <form method="POST" action="{{ route('admin.wallet.salary') }}">
                    @csrf
                    <p class="font-weight-bold mb-1">Pay Salary to Employee Driver</p>
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 mb-0">
                            <label class="small">Driver</label>
                            <select name="user_id" class="form-control form-control-sm" required>
                                <option value="">— Select driver —</option>
                                @foreach(\App\Models\User::where('role','driver')->where('driver_type','employee')->orderBy('name')->get() as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label class="small">Amount (KHR ៛)</label>
                            <input type="number" name="amount" class="form-control form-control-sm" min="1000" step="1000" required placeholder="e.g. 500000">
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label class="small">Note</label>
                            <input type="text" name="note" class="form-control form-control-sm" placeholder="Monthly salary">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-success btn-block"><i class="fas fa-money-bill-wave"></i> Pay</button>
                        </div>
                    </div>
                </form>
            </div>
            {{-- Manual credit --}}
            <div class="col-md-6">
                <form method="POST" action="{{ route('admin.wallet.credit') }}">
                    @csrf
                    <p class="font-weight-bold mb-1">Manual Bonus / Adjustment</p>
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3 mb-0">
                            <label class="small">Driver / User</label>
                            <select name="user_id" class="form-control form-control-sm" required>
                                <option value="">— Select —</option>
                                @foreach(\App\Models\User::where('role','driver')->orderBy('name')->get() as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <label class="small">Amount (KHR)</label>
                            <input type="number" name="amount" class="form-control form-control-sm" min="100" step="100" required>
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label class="small">Type</label>
                            <select name="type" class="form-control form-control-sm">
                                <option value="bonus">Bonus</option>
                                <option value="adjustment">Adjustment</option>
                                <option value="top_up">Top-up</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <label class="small">Note</label>
                            <input type="text" name="note" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary btn-block"><i class="fas fa-plus"></i> Credit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Transaction ledger --}}
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-list-alt mr-1"></i> All Transactions</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Driver / User</th>
                    <th>Type</th>
                    <th>Direction</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                @php
                    $typeColor = [
                        'top_up'              => 'success',
                        'trip_earning'        => 'success',
                        'salary'              => 'success',
                        'bonus'               => 'success',
                        'platform_commission' => 'warning',
                        'company_commission'  => 'warning',
                        'rental_fee'          => 'warning',
                        'withdrawal'          => 'danger',
                        'adjustment'          => 'info',
                    ];
                @endphp
                <tr>
                    <td>{{ $tx->id }}</td>
                    <td>{{ $tx->user->name }}</td>
                    <td>
                        <span class="badge badge-{{ $typeColor[$tx->type] ?? 'secondary' }}">
                            {{ \App\Models\WalletTransaction::$typeLabels[$tx->type] ?? ucfirst($tx->type) }}
                        </span>
                    </td>
                    <td>
                        @if($tx->direction === 'credit')
                            <span class="text-success"><i class="fas fa-arrow-up"></i> Credit</span>
                        @else
                            <span class="text-danger"><i class="fas fa-arrow-down"></i> Debit</span>
                        @endif
                    </td>
                    <td>
                        <strong class="{{ $tx->direction === 'credit' ? 'text-success' : 'text-danger' }}">
                            {{ $tx->direction === 'credit' ? '+' : '-' }}{{ number_format($tx->amount, 0) }} ៛
                        </strong>
                    </td>
                    <td>{{ number_format($tx->balance_after, 0) }} ៛</td>
                    <td>{{ \Illuminate\Support\Str::limit($tx->note ?? '—', 40) }}</td>
                    <td>{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $transactions->links() }}</div>
</div>
@endsection
