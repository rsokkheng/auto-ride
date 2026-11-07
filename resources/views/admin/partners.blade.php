@extends('admin.layout')
@section('title', 'Partner Registrations')
@section('page-title', 'Partner Registrations')

@section('content')

<x-admin.page-toolbar subtitle="Passengers who registered to send packages through ROTEH. Approved Partners see Create Package, My Packages and tracking in the Passenger app — and keep booking rides.">
    <a href="{{ route('admin.partner-packages') }}" class="btn btn-outline-primary"><i class="fas fa-boxes-stacked mr-1"></i> Partner Packages</a>
    <a href="{{ route('admin.partner-contracts') }}" class="btn btn-outline-secondary"><i class="fas fa-file-contract mr-1"></i> Contracts &amp; Prices</a>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Waiting Review" :value="number_format($stats['pending'] ?? 0)" icon="fa-hourglass-half" tone="warning"
                       :href="route('admin.partners', ['status' => 'pending'])" :active="request('status') === 'pending'" />
    <x-admin.stat-card label="Approved" :value="number_format($stats['approved'] ?? 0)" icon="fa-handshake" tone="success"
                       :href="route('admin.partners', ['status' => 'approved'])" :active="request('status') === 'approved'" />
    <x-admin.stat-card label="Rejected" :value="number_format($stats['rejected'] ?? 0)" icon="fa-circle-xmark" tone="danger"
                       :href="route('admin.partners', ['status' => 'rejected'])" :active="request('status') === 'rejected'" />
    <x-admin.stat-card label="Suspended" :value="number_format($stats['suspended'] ?? 0)" icon="fa-ban" tone="muted"
                       :href="route('admin.partners', ['status' => 'suspended'])" :active="request('status') === 'suspended'" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.partners')" placeholder="Search business, phone, name or email" :count="$profiles->total()" noun="partner">
            <x-admin.filter-select name="status" :options="['pending' => 'Waiting review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended']" all="All statuses" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Account</th>
                    <th>Pickup address</th>
                    <th class="text-right">Packages</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($profiles as $p)
                @php($tone = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'suspended' => 'muted'][$p->status] ?? 'muted')
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $p->business_name }}</div>
                        <div class="text-muted tw:text-xs">{{ $p->business_type ? ucfirst($p->business_type) . ' · ' : '' }}{{ $p->phone }}{{ $p->contact_name ? ' · ' . $p->contact_name : '' }}</div>
                    </td>
                    <td>
                        <div>{{ $p->user->name ?? '—' }}</div>
                        <div class="text-muted tw:text-xs">{{ $p->user->email ?? '' }}</div>
                    </td>
                    <td class="text-wrap" style="max-width:260px;">
                        {{ $p->address ?? '—' }}
                        @if($p->latitude && $p->longitude)
                            <a href="https://maps.google.com/?q={{ $p->latitude }},{{ $p->longitude }}" target="_blank" class="tw:text-xs ml-1"><i class="fas fa-map-marker-alt"></i></a>
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('admin.partner-packages', ['search' => $p->user->name ?? '']) }}">{{ number_format($p->packages_count ?? 0) }}</a>
                    </td>
                    <td>
                        <span class="status-pill tone-{{ $tone }}">{{ ucfirst($p->status) }}</span>
                        @if($p->status === 'rejected' && $p->rejection_reason)
                            <div class="text-muted tw:text-xs text-wrap" style="max-width:200px;">{{ $p->rejection_reason }}</div>
                        @endif
                        <div class="text-muted tw:text-xs">{{ ($p->reviewed_at ?? $p->created_at)?->format('d M Y H:i') }}</div>
                    </td>
                    <td class="text-center">
                        @if($p->status !== 'approved')
                            <form method="POST" action="{{ route('admin.partners.approve', $p) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" onclick="return confirm('Approve {{ addslashes($p->business_name) }} as a Partner?')"><i class="fas fa-check mr-1"></i>Approve</button>
                            </form>
                        @endif
                        @if($p->status === 'pending' || $p->status === 'approved')
                            <button class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#rejectModal"
                                    data-action="{{ route('admin.partners.reject', $p) }}" data-name="{{ $p->business_name }}"><i class="fas fa-times mr-1"></i>Reject</button>
                        @endif
                        @if($p->status === 'approved')
                            <form method="POST" action="{{ route('admin.partners.suspend', $p) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" onclick="return confirm('Suspend {{ addslashes($p->business_name) }}? They will not be able to create packages.')"><i class="fas fa-ban mr-1"></i>Suspend</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-handshake" title="No Partner registrations"
                                   hint="Passengers register from the Profile tab of the ROTEH app." :clear="route('admin.partners')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$profiles" />
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="rejectForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Reject <span id="rejectName"></span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <label for="rejectReason">Reason (sent to the user)</label>
                <textarea name="reason" id="rejectReason" class="form-control" rows="3" maxlength="500" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$('#rejectModal').on('show.bs.modal', function (e) {
    const b = $(e.relatedTarget);
    $('#rejectForm').attr('action', b.data('action'));
    $('#rejectName').text(b.data('name'));
    $('#rejectReason').val('');
});
</script>
@endpush
