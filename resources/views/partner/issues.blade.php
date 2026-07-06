@extends('partner.layout')
@section('title', 'Issues')
@section('page-title', 'Issues & Failed Deliveries')

@section('content')

<div class="card mb-3" style="border-left:4px solid #ef4444">
    <div class="card-body py-2 d-flex align-items-center">
        <i class="fas fa-exclamation-triangle text-danger mr-3 fa-lg"></i>
        <div>
            <strong>Failed &amp; Cancelled Orders</strong>
            <div class="small text-muted">Orders that were cancelled or could not be delivered. Contact support if you need assistance resolving an issue.</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-times-circle text-danger mr-2"></i>Cancelled Orders</h5>
        <span class="badge badge-danger">{{ $failed->total() }}</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>#</th><th>Recipient</th><th>Dropoff</th><th>Fee</th>
                    <th>Driver</th><th>Reason</th><th>Date</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($failed as $d)
                <tr>
                    <td class="text-muted small">{{ $d->id }}</td>
                    <td>
                        <div class="font-weight-bold">{{ $d->recipient_name }}</div>
                        <small class="text-muted">{{ $d->recipient_phone }}</small>
                    </td>
                    <td><small class="text-muted">{{ Str::limit($d->dropoff_address, 28) }}</small></td>
                    <td>{{ number_format($d->fee) }} ៛</td>
                    <td>
                        @if($d->driver)
                            <div>{{ $d->driver->name }}</div>
                            <small class="text-muted">{{ $d->driver->phone }}</small>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>
                        @if($d->cancellation_reason)
                            <small class="text-danger">{{ Str::limit($d->cancellation_reason, 30) }}</small>
                        @else
                            <small class="text-muted">—</small>
                        @endif
                    </td>
                    <td><small class="text-muted">{{ $d->created_at->format('d M H:i') }}</small></td>
                    <td>
                        <a href="{{ route('partner.orders.show', $d) }}" class="btn btn-xs btn-outline-secondary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-5">
                    <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                    No cancelled orders. Great job!
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($failed->hasPages())
    <div class="card-footer">{{ $failed->links() }}</div>
    @endif
</div>

<div class="card mt-3">
    <div class="card-body">
        <h6 class="font-weight-bold"><i class="fas fa-headset text-primary mr-2"></i>Need Help?</h6>
        <p class="text-muted mb-2">If you have unresolved delivery issues, please contact your AutoRide account manager or reach out through:</p>
        <ul class="text-muted small mb-0">
            <li>Phone/Telegram: provided by your account manager</li>
            <li>Email: support@autoride.com.kh</li>
        </ul>
    </div>
</div>
@endsection
