@extends('admin.layout')
@section('title', 'Subscribers — ' . $plan->name)
@section('page-title', $plan->name . ' Subscribers')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <span class="badge mr-2" style="background:{{ $plan->badge_color }};color:#fff">
                <i class="{{ $plan->icon }}"></i>
            </span>
            {{ $plan->name }} — {{ number_format($plan->price_khr) }} ៛ / {{ $plan->billing_cycle }}
        </h3>
        <a href="{{ route('admin.subscription-plans') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Plans
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Expires</th>
                    <th>Credit Used</th>
                    <th>Cancellations</th>
                    <th>Auto-Renew</th>
                    <th>Renewals</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscribers as $sub)
                <tr>
                    <td>
                        <strong>{{ $sub->user->name ?? '—' }}</strong><br>
                        <small class="text-muted">{{ $sub->user->email ?? '' }}</small>
                    </td>
                    <td>
                        <span class="badge badge-{{ $sub->status === 'active' ? 'success' : ($sub->status === 'cancelled' ? 'warning' : 'secondary') }}">
                            {{ ucfirst($sub->status) }}
                        </span>
                    </td>
                    <td>{{ $sub->started_at?->format('d M Y') }}</td>
                    <td>
                        {{ $sub->expires_at?->format('d M Y') }}
                        @if($sub->isActive() && $sub->expiresInDays() <= 3)
                            <span class="badge badge-danger ml-1">{{ $sub->expiresInDays() }}d left</span>
                        @endif
                    </td>
                    <td>{{ number_format($sub->used_ride_credit_khr) }} ៛</td>
                    <td>{{ $sub->used_cancellations }}</td>
                    <td>
                        <span class="badge badge-{{ $sub->auto_renew ? 'success' : 'secondary' }}">
                            {{ $sub->auto_renew ? 'On' : 'Off' }}
                        </span>
                    </td>
                    <td class="text-center">{{ $sub->renewal_count }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No subscribers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($subscribers->hasPages())
    <div class="card-footer">{{ $subscribers->links() }}</div>
    @endif
</div>
@endsection
