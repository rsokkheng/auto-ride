@extends('admin.layout')
@section('title', 'Partner Packages')
@section('page-title', 'Partner Packages')

@section('content')

<x-admin.page-toolbar subtitle="Packages created by Partners. Tick packages and assign them to a ROTEH staff driver — they appear on the driver's “My packages” list (no broadcast).">
    <a href="{{ route('admin.partners') }}" class="btn btn-outline-primary"><i class="fas fa-handshake mr-1"></i> Partner Registrations</a>
</x-admin.page-toolbar>

@php($labels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'driver_assigned' => 'Driver Assigned', 'picked_up' => 'Picked Up', 'in_transit' => 'In Transit', 'completed' => 'Completed', 'failed' => 'Not Delivered — returning', 'returned' => 'Returned to Shop', 'cancelled' => 'Cancelled'])
@php($tones  = ['pending' => 'warning', 'confirmed' => 'info', 'driver_assigned' => 'primary', 'picked_up' => 'primary', 'in_transit' => 'primary', 'completed' => 'success', 'failed' => 'danger', 'returned' => 'muted', 'cancelled' => 'danger'])
@php($reasons = ['cannot_contact' => 'Cannot contact receiver', 'receiver_refused' => 'Receiver refused', 'wrong_address' => 'Wrong address', 'receiver_postponed' => 'Receiver asked to postpone', 'other' => 'Other'])

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Waiting Confirmation" :value="number_format($stats['pending'])" icon="fa-hourglass-half" tone="warning"
                       :href="route('admin.partner-packages', ['status' => 'pending'])" :active="request('status') === 'pending'" />
    <x-admin.stat-card label="Confirmed — to assign" :value="number_format($stats['confirmed'])" icon="fa-user-clock" tone="info"
                       :href="route('admin.partner-packages', ['status' => 'confirmed'])" :active="request('status') === 'confirmed'" />
    <x-admin.stat-card label="On the Way" :value="number_format($stats['driver_assigned'] + $stats['picked_up'] + $stats['in_transit'])" icon="fa-truck-fast" tone="primary" />
    <x-admin.stat-card label="Completed" :value="number_format($stats['completed'])" icon="fa-circle-check" tone="success"
                       :href="route('admin.partner-packages', ['status' => 'completed'])" :active="request('status') === 'completed'"
                       :sub="number_format($stats['failed'] + $stats['returned']) . ' not delivered / returned'" />
</x-admin.stat-grid>

{{-- Bulk assign: tick packages → choose a ROTEH staff driver → Assign --}}
<form method="POST" action="{{ route('admin.partner-packages.assign') }}" id="assignForm">
    @csrf
    <div class="card mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center" style="gap:8px;">
            <i class="fas fa-motorcycle text-muted"></i>
            <b class="mr-1">Assign to ROTEH staff</b>
            <span class="text-muted tw:text-xs" id="selCount">0 selected</span>
            <select name="driver_id" class="form-control form-control-sm" style="width:auto;min-width:240px;" required>
                <option value="">— Choose staff driver —</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} · {{ $s->phone }} ({{ $staffLoad[$s->id] ?? 0 }} on hand)</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-success" id="assignBtn" disabled><i class="fas fa-user-check mr-1"></i>Assign</button>
            @if($staff->isEmpty())
                <span class="text-danger tw:text-xs">No ROTEH staff drivers yet — create one in Users (type: ROTEH Staff).</span>
            @endif
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.partner-packages')" placeholder="Search PKG code, partner, recipient or reference" :count="$packages->total()" noun="package">
            <x-admin.filter-select name="status" :options="$labels" all="All statuses" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th style="width:28px;"><input type="checkbox" id="selAll" title="Select all assignable"></th>
                    <th>Package</th>
                    <th>Partner</th>
                    <th>Route</th>
                    <th>Recipient</th>
                    <th class="text-right">Fee / COD</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($packages as $d)
                @php($ps = $d->packageStatus())
                @php($assignable = in_array($d->status, \App\Services\StaffAssignmentService::ASSIGNABLE, true) && ! $d->pickup_scanned_at)
                <tr>
                    <td>@if($assignable)<input type="checkbox" class="sel" form="assignForm" name="ids[]" value="{{ $d->id }}">@endif</td>
                    <td>
                        <a href="{{ route('admin.deliveries.show', $d) }}" style="font-weight:600;">PKG-{{ str_pad($d->id, 6, '0', STR_PAD_LEFT) }}</a>
                        <div class="text-muted tw:text-xs">
                            {{ ucfirst($d->package_type ?? 'parcel') }} · {{ ucfirst(str_replace('_', ' ', $d->package_size ?? 'small')) }}{{ $d->package_weight ? ' · ' . rtrim(rtrim(number_format($d->package_weight, 2), '0'), '.') . ' kg' : '' }}
                            @if($d->service_option === 'express') · <span class="text-danger"><i class="fas fa-bolt"></i> Express</span>@endif
                        </div>
                        @if($d->partner_reference)<div class="text-muted tw:text-xs">Ref: {{ $d->partner_reference }}</div>@endif
                    </td>
                    <td>
                        <div>{{ $d->partner->company_name ?: ($d->partner->name ?? '—') }}</div>
                        <div class="text-muted tw:text-xs">{{ $d->sender_name }} · {{ $d->sender_phone }}</div>
                    </td>
                    <td class="text-wrap" style="max-width:260px;">
                        <div class="tw:text-xs"><i class="fas fa-circle text-success mr-1" style="font-size:7px;"></i>{{ \Illuminate\Support\Str::limit($d->pickup_address, 60) }}</div>
                        <div class="tw:text-xs"><i class="fas fa-location-dot text-danger mr-1"></i>{{ \Illuminate\Support\Str::limit($d->dropoff_address, 60) }}</div>
                    </td>
                    <td>
                        <div>{{ $d->recipient_name }}</div>
                        <div class="text-muted tw:text-xs">{{ $d->recipient_phone }}</div>
                    </td>
                    <td class="text-right">
                        <div style="font-weight:600;">{{ number_format($d->fee) }} ៛</div>
                        <div class="text-muted tw:text-xs">{{ $d->payment_by === 'recipient' ? 'Recipient pays' : 'Partner pays' }}{{ $d->package_amount > 0 ? ' · COD ' . number_format($d->package_amount) . ' ៛' : '' }}</div>
                    </td>
                    <td>
                        <span class="status-pill tone-{{ $tones[$ps] ?? 'muted' }}">{{ $labels[$ps] ?? ucfirst($ps) }}</span>
                        @if(in_array($ps, ['failed', 'returned']) && $d->failure_reason)<div class="text-danger tw:text-xs">{{ $reasons[$d->failure_reason] ?? $d->failure_reason }}</div>@endif
                        @if($d->driver)<div class="text-muted tw:text-xs"><i class="fas fa-motorcycle mr-1"></i>{{ $d->driver->name }}</div>@endif
                        <div class="text-muted tw:text-xs">{{ $d->created_at->format('d M H:i') }}</div>
                    </td>
                    <td class="text-center">
                        @if($ps === 'pending')
                            <form method="POST" action="{{ route('admin.partner-packages.confirm', $d) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success" title="Confirm without assigning yet"><i class="fas fa-check mr-1"></i>Confirm</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.deliveries.show', $d) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="8" icon="fa-boxes-stacked" title="No Partner packages"
                                   hint="Approved Partners create packages from the ROTEH app." :clear="route('admin.partner-packages')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$packages" />
</div>
@endsection

@push('scripts')
<script>
(function () {
    const boxes = () => Array.from(document.querySelectorAll('.sel'));
    const sync = () => {
        const n = boxes().filter(b => b.checked).length;
        document.getElementById('selCount').textContent = n + ' selected';
        document.getElementById('assignBtn').disabled = n === 0;
    };
    document.addEventListener('change', e => {
        if (e.target.id === 'selAll') boxes().forEach(b => b.checked = e.target.checked);
        if (e.target.classList.contains('sel') || e.target.id === 'selAll') sync();
    });
})();
</script>
@endpush
