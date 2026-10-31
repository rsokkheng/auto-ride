@extends('admin.layout')
@section('title', 'Driver Approvals')
@section('page-title', 'Driver Approvals')

@section('content')

<x-admin.page-toolbar subtitle="Review new driver applications and their documents, and manage penalties.">
    <a href="{{ route('admin.users', ['role' => 'driver']) }}" class="btn btn-secondary"><i class="fas fa-users mr-1"></i> Driver accounts</a>
</x-admin.page-toolbar>

@php
    $cards = [
        'all'       => ['All Drivers', 'fa-id-card',            'primary'],
        'pending'   => ['Pending Review', 'fa-hourglass-half',   'warning'],
        'approved'  => ['Approved', 'fa-circle-check',           'success'],
        'rejected'  => ['Rejected', 'fa-circle-xmark',           'muted'],
        'penalized' => ['Penalized', 'fa-ban',                   'danger'],
    ];
    $dtLabel  = ['owner' => 'Own vehicle', 'rental' => 'Rental', 'employee' => 'Employee'];
    $initials = fn (?string $name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '?';
    $required = 4;
@endphp

<x-admin.stat-grid :cols="5">
    @foreach($cards as $key => [$label, $icon, $tone])
        <x-admin.stat-card :label="$label" :value="number_format($counts[$key])" :icon="$icon" :tone="$tone"
                           :href="route('admin.drivers', array_filter(['status' => $key, 'search' => $search]))" :active="$status === $key" />
    @endforeach
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.drivers')" placeholder="Search name, phone or email" :count="$drivers->total()" noun="driver">
            <x-admin.filter-select name="status" :options="collect($cards)->except('all')->map(fn ($c) => $c[0])->all()" all="All statuses"
                                  :value="$status === 'all' ? '' : $status" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Driver</th>
                    <th>Phone</th>
                    <th>Area</th>
                    <th>Type</th>
                    <th>Documents</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($drivers as $driver)
                @php
                    $docs = $driver->driver_documents_count;
                    [$sLabel, $sTone] = match ($driver->approval_status) {
                        'approved' => ['Approved', 'success'], 'rejected' => ['Rejected', 'muted'], default => ['Pending', 'warning'],
                    };
                @endphp
                <tr>
                    <td>
                        <div class="person">
                            @if($driver->avatar)
                                <img src="{{ asset('storage/' . $driver->avatar) }}" alt="" class="avatar-chip" style="object-fit:cover;" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'), { className: 'avatar-chip', textContent: {{ Illuminate\Support\Js::from($initials($driver->name)) }} }))">
                            @else
                                <span class="avatar-chip">{{ $initials($driver->name) }}</span>
                            @endif
                            <div class="tw:min-w-0">
                                <a href="{{ route('admin.drivers.show', $driver->id) }}" class="person-name d-block" style="max-width:200px;">{{ $driver->name }}</a>
                                <div class="person-sub text-truncate" style="max-width:200px;">{{ $driver->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $driver->phone ?? '—' }}</td>
                    <td>
                        <div>{{ $driver->city ?? '—' }}</div>
                        @if($driver->service_zone)<div class="text-muted tw:text-xs">{{ $driver->service_zone }}</div>@endif
                    </td>
                    <td>
                        @if($driver->driver_type)
                            <span class="badge badge-info">{{ $dtLabel[$driver->driver_type] ?? ucfirst($driver->driver_type) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="min-width:110px;">
                        <div class="tw:text-xs"><b style="color:var(--ink-900);">{{ $docs }}</b> / {{ $required }} uploaded</div>
                        <div style="height:5px;border-radius:9px;background:var(--ink-100);margin-top:4px;overflow:hidden;">
                            <span style="display:block;height:100%;width:{{ min(100, $docs / $required * 100) }}%;background:{{ $docs >= $required ? 'var(--success)' : 'var(--warning)' }};"></span>
                        </div>
                    </td>
                    <td>
                        <span class="status-pill tone-{{ $sTone }}">{{ $sLabel }}</span>
                        @if($driver->isPenalized())
                            <div class="tw:mt-1"><span class="badge badge-danger" title="{{ $driver->penalty_reason }}" data-toggle="tooltip">
                                <i class="fas fa-ban mr-1"></i>Penalized until {{ $driver->penalty_until->format('d M, H:i') }}
                            </span></div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $driver->created_at->format('d M Y') }}</div>
                        <div class="text-muted tw:text-xs">{{ $driver->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="text-center">
                        <div class="action-group">
                            <a href="{{ route('admin.drivers.show', $driver->id) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye mr-1"></i> Review
                            </a>
                            @if($driver->isPenalized())
                                <form method="POST" action="{{ route('admin.drivers.penalty.clear', $driver) }}" class="m-0"
                                      onsubmit="return confirm({{ Illuminate\Support\Js::from('Clear the penalty for ' . $driver->name . '?') }})">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-secondary btn-icon" title="Clear penalty" data-toggle="tooltip"><i class="fas fa-rotate-left"></i></button>
                                </form>
                            @else
                                <button class="btn btn-sm btn-outline-warning btn-icon" title="Penalize" data-toggle="tooltip"
                                        onclick="openPenalize({{ $driver->id }}, {{ Illuminate\Support\Js::from($driver->name) }})">
                                    <i class="fas fa-ban"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="8" icon="fa-id-card"
                                   :title="$status === 'pending' ? 'No drivers waiting for review' : 'No drivers here'"
                                   :hint="$status === 'pending' ? 'New applications will appear here.' : null" :clear="route('admin.drivers', ['status' => 'all'])" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$drivers" />
</div>

{{-- Penalize driver modal --}}
<div class="modal fade" id="penalizeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Penalize <span id="p-driver-name"></span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="penalizeForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted" style="font-size:.85rem;">
                        The driver won't be able to go online or receive ride requests until the penalty expires.
                    </p>
                    <div class="form-group">
                        <label>Duration</label>
                        <div class="btn-group-toggle d-flex flex-wrap" style="gap:6px;" data-toggle="buttons">
                            @foreach([
                                ['label' => '2 Hours', 'hours' => 2],
                                ['label' => '4 Hours', 'hours' => 4],
                                ['label' => '12 Hours', 'hours' => 12],
                                ['label' => '1 Day', 'hours' => 24],
                                ['label' => '3 Days', 'hours' => 72],
                                ['label' => '1 Week', 'hours' => 168],
                            ] as $preset)
                                <button type="button" class="btn btn-outline-danger btn-sm preset-btn" data-hours="{{ $preset['hours'] }}" onclick="selectPreset(this)">
                                    {{ $preset['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Custom Hours <small class="text-muted">(overrides preset above)</small></label>
                        <input type="number" name="hours" id="p-hours" class="form-control" step="0.5" min="0.5" max="8760" required>
                    </div>
                    <div class="form-group">
                        <label>Reason <small class="text-muted">(shown to the driver)</small></label>
                        <textarea name="reason" id="p-reason" class="form-control" rows="2" maxlength="255"
                                  placeholder="e.g. Customer complaint — rude behaviour on ride #1234"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban mr-1"></i> Apply Penalty</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const penalizeBaseUrl = '{{ url("admin/drivers") }}/';

function openPenalize(driverId, driverName) {
    document.getElementById('p-driver-name').textContent = driverName;
    document.getElementById('penalizeForm').action = penalizeBaseUrl + driverId + '/penalize';
    document.getElementById('penalizeForm').reset();
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
    $('#penalizeModal').modal('show');
}

function selectPreset(btn) {
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('p-hours').value = btn.getAttribute('data-hours');
}
</script>
@endpush
