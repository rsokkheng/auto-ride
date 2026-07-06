@extends('partner.layout')
@section('title', 'Issues')
@section('page-title', 'Issues & Failed Deliveries')

@section('content')

{{-- ── Alert Banner ─────────────────────────────────────────────── --}}
<div class="flex items-start gap-4 bg-red-50 border border-red-200 rounded-2xl px-5 py-4 mb-5">
    <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
        <i class="fas fa-exclamation-triangle text-red-600"></i>
    </div>
    <div>
        <p class="font-semibold text-red-700 mb-0.5">Failed &amp; Cancelled Orders</p>
        <p class="text-sm text-red-600 mb-0">
            Orders that were cancelled or could not be delivered.
            Contact support if you need help resolving an issue.
        </p>
    </div>
</div>

{{-- ── Cancelled Orders Table ───────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 mb-0">
            <i class="fas fa-times-circle text-red-500"></i> Cancelled Orders
        </h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
            {{ $failed->total() }} total
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
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
                        <div class="fw-semibold">{{ $d->recipient_name }}</div>
                        <small class="text-muted">{{ $d->recipient_phone }}</small>
                    </td>
                    <td><small class="text-muted">{{ Str::limit($d->dropoff_address, 28) }}</small></td>
                    <td>{{ number_format($d->fee) }} ៛</td>
                    <td>
                        @if($d->driver)
                            <div class="small fw-semibold">{{ $d->driver->name }}</div>
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
                        <a href="{{ route('partner.orders.show', $d) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-12">
                        <i class="fas fa-check-circle fa-2x text-emerald-400 mb-2 d-block"></i>
                        <p class="text-slate-500 mb-0">No cancelled orders. Great job!</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($failed->hasPages())
    <div class="px-5 py-3 border-t border-slate-100">{{ $failed->links() }}</div>
    @endif
</div>

{{-- ── Support Card ─────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-headset text-blue-600"></i>
        </div>
        <h3 class="font-semibold text-slate-800 mb-0">Need Help?</h3>
    </div>
    <p class="text-slate-500 text-sm mb-4">
        If you have unresolved delivery issues, please contact your AutoRide account manager or reach out through:
    </p>
    <div class="grid sm:grid-cols-2 gap-3">
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 border border-slate-200">
            <i class="fas fa-phone-alt text-slate-400"></i>
            <div>
                <p class="text-xs text-slate-400 mb-0">Phone / Telegram</p>
                <p class="text-sm font-medium text-slate-700 mb-0">Provided by your account manager</p>
            </div>
        </div>
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 border border-slate-200">
            <i class="fas fa-envelope text-slate-400"></i>
            <div>
                <p class="text-xs text-slate-400 mb-0">Email</p>
                <p class="text-sm font-medium text-slate-700 mb-0">support@autoride.com.kh</p>
            </div>
        </div>
    </div>
</div>

@endsection
