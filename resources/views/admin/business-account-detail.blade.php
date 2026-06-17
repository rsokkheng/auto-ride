@extends('admin.layout')
@section('title', $account->name)
@section('page-title', $account->name)

@section('content')
<div class="row">
    {{-- Account info --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Account Info</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Code</dt><dd class="col-7"><code>{{ $account->code }}</code></dd>
                    <dt class="col-5">Industry</dt><dd class="col-7">{{ $account->industry ?? '—' }}</dd>
                    <dt class="col-5">Tax ID</dt><dd class="col-7">{{ $account->tax_id ?? '—' }}</dd>
                    <dt class="col-5">Billing</dt><dd class="col-7">{{ ucfirst($account->billing_cycle) }}</dd>
                    <dt class="col-5">Billing email</dt><dd class="col-7">{{ $account->billing_email ?? '—' }}</dd>
                    <dt class="col-5">Owner</dt><dd class="col-7">{{ $account->owner->name ?? '—' }}</dd>
                    <dt class="col-5">Contact</dt><dd class="col-7">{{ $account->contact_name ?? '—' }}<br>{{ $account->contact_phone ?? '' }}</dd>
                    <dt class="col-5">Status</dt>
                    <dd class="col-7">
                        <span class="badge badge-{{ $account->active ? 'success' : 'secondary' }}">
                            {{ $account->active ? 'Active' : 'Inactive' }}
                        </span>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title mb-0">Credit Usage</h3></div>
            <div class="card-body">
                @php
                    $pct = $account->monthly_credit_limit_khr > 0
                        ? round($account->used_credit_khr / $account->monthly_credit_limit_khr * 100)
                        : 0;
                @endphp
                <div class="progress mb-2" style="height:12px">
                    <div class="progress-bar bg-{{ $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success') }}"
                         style="width:{{ $pct }}%">{{ $pct }}%</div>
                </div>
                <p class="mb-0 small">
                    Used: <strong>{{ number_format($account->used_credit_khr) }} ៛</strong><br>
                    Limit: <strong>{{ number_format($account->monthly_credit_limit_khr) }} ៛</strong><br>
                    Remaining: <strong>{{ number_format($account->remainingCreditKhr()) }} ៛</strong>
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        {{-- Members --}}
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0">Members ({{ $account->members->count() }})</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Limit / mo</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        @forelse($account->members as $m)
                        <tr>
                            <td>{{ $m->user->name ?? '—' }}</td>
                            <td>{{ $m->user->email ?? '—' }}</td>
                            <td><span class="badge badge-{{ $m->role === 'admin' ? 'primary' : 'secondary' }}">{{ $m->role }}</span></td>
                            <td>{{ $m->department ?? '—' }}</td>
                            <td>{{ $m->monthly_limit_khr ? number_format($m->monthly_limit_khr) . ' ៛' : 'Unlimited' }}</td>
                            <td>{{ $m->joined_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent rides --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Recent Business Trips</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr><th>Ride #</th><th>Passenger</th><th>Date</th><th>Category</th><th>Fare</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentRides as $ride)
                        <tr>
                            <td>#{{ $ride->id }}</td>
                            <td>{{ $ride->passenger->name ?? '—' }}</td>
                            <td>{{ $ride->completed_at?->format('d M Y') }}</td>
                            <td>{{ $ride->expense_category ?? '—' }}</td>
                            <td>{{ number_format($ride->fare) }} ៛</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No trips yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
