@extends('admin.layout')
@section('title', 'Partner Packages')
@section('page-title', 'Partner Packages')

@section('content')

<x-admin.page-toolbar subtitle="Packages created by Partners in the ROTEH app. Confirm a pending package to send it to nearby drivers, or open it to assign a driver yourself.">
    <a href="{{ route('admin.partners') }}" class="btn btn-outline-primary"><i class="fas fa-handshake mr-1"></i> Partner Registrations</a>
</x-admin.page-toolbar>

@php($labels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'driver_assigned' => 'Driver Assigned', 'picked_up' => 'Picked Up', 'in_transit' => 'In Transit', 'completed' => 'Completed', 'failed' => 'Not Delivered — returning', 'returned' => 'Returned to Shop', 'cancelled' => 'Cancelled'])
@php($tones  = ['pending' => 'warning', 'confirmed' => 'info', 'driver_assigned' => 'primary', 'picked_up' => 'primary', 'in_transit' => 'primary', 'completed' => 'success', 'failed' => 'danger', 'returned' => 'muted', 'cancelled' => 'danger'])
@php($reasons = ['cannot_contact' => 'Cannot contact receiver', 'receiver_refused' => 'Receiver refused', 'wrong_address' => 'Wrong address', 'receiver_postponed' => 'Receiver asked to postpone', 'other' => 'Other'])

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Waiting Confirmation" :value="number_format($stats['pending'])" icon="fa-hourglass-half" tone="warning"
                       :href="route('admin.partner-packages', ['status' => 'pending'])" :active="request('status') === 'pending'" />
    <x-admin.stat-card label="Finding Driver" :value="number_format($stats['confirmed'])" icon="fa-magnifying-glass-location" tone="info"
                       :href="route('admin.partner-packages', ['status' => 'confirmed'])" :active="request('status') === 'confirmed'" />
    <x-admin.stat-card label="On the Way" :value="number_format($stats['driver_assigned'] + $stats['picked_up'] + $stats['in_transit'])" icon="fa-truck-fast" tone="primary" />
    <x-admin.stat-card label="Completed" :value="number_format($stats['completed'])" icon="fa-circle-check" tone="success"
                       :href="route('admin.partner-packages', ['status' => 'completed'])" :active="request('status') === 'completed'"
                       :sub="number_format($stats['failed'] + $stats['returned']) . ' not delivered / returned'" />
</x-admin.stat-grid>

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
                <tr>
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
                                <button class="btn btn-sm btn-success"><i class="fas fa-check mr-1"></i>Confirm</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.deliveries.show', $d) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="7" icon="fa-boxes-stacked" title="No Partner packages"
                                   hint="Approved Partners create packages from the ROTEH app." :clear="route('admin.partner-packages')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$packages" />
</div>
@endsection
