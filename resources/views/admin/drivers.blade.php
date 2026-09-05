@extends('admin.layout')
@section('title', 'Driver Approvals')
@section('page-title', 'Driver Approvals')

@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.drivers') }}" class="form-inline flex-wrap" style="gap:6px">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search by name, phone, or email…"
                   value="{{ $search ?? '' }}" style="min-width:260px">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i>Search</button>
            @if(($search ?? '') !== '')
                <a href="{{ route('admin.drivers', ['status' => $status]) }}" class="btn btn-sm btn-secondary">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.drivers', ['status' => 'pending', 'search' => $search]) }}">
                    Pending
                    @if($counts['pending'] > 0)
                        <span class="badge badge-danger ml-1">{{ $counts['pending'] }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('admin.drivers', ['status' => 'approved', 'search' => $search]) }}">
                    Approved
                    @if($counts['approved'] > 0)
                        <span class="badge badge-success ml-1">{{ $counts['approved'] }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('admin.drivers', ['status' => 'rejected', 'search' => $search]) }}">
                    Rejected
                    @if($counts['rejected'] > 0)
                        <span class="badge badge-secondary ml-1">{{ $counts['rejected'] }}</span>
                    @endif
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Driver</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Service Zone</th>
                        <th>Driver Type</th>
                        <th>Documents</th>
                        <th>Penalty</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drivers as $driver)
                    <tr>
                        <td>{{ ($drivers->currentPage() - 1) * $drivers->perPage() + $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                @if($driver->avatar)
                                    <img src="{{ asset('storage/' . $driver->avatar) }}" class="img-circle mr-2" style="width:32px;height:32px;object-fit:cover;">
                                @else
                                    <div class="img-circle mr-2 bg-secondary d-flex align-items-center justify-content-center text-white" style="width:32px;height:32px;font-size:.75rem;">
                                        {{ strtoupper(substr($driver->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="font-weight-bold">{{ $driver->name }}</div>
                                    <small class="text-muted">{{ $driver->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $driver->phone ?? '—' }}</td>
                        <td>{{ $driver->city ?? '—' }}</td>
                        <td>{{ $driver->service_zone ?? '—' }}</td>
                        <td>
                            <span class="badge badge-info">{{ $driver->driver_type ?? 'N/A' }}</span>
                        </td>
                        <td>
                            @php
                                $docCount = $driver->driver_documents_count;
                                $required = 4;
                            @endphp
                            <span class="badge {{ $docCount >= $required ? 'badge-success' : 'badge-warning' }}">
                                {{ $docCount }} / {{ $required }}
                            </span>
                        </td>
                        <td>
                            @if($driver->isPenalized())
                                <span class="badge badge-danger" title="{{ $driver->penalty_reason }}">
                                    Until {{ $driver->penalty_until->format('d M, g:i A') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $driver->created_at->format('d M Y') }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.drivers.show', $driver->id) }}" class="btn btn-sm btn-primary mb-1">
                                <i class="fas fa-eye mr-1"></i> Review
                            </a>
                            @if($driver->isPenalized())
                                <form method="POST" action="{{ route('admin.drivers.penalty.clear', $driver) }}" class="d-inline"
                                      onsubmit="return confirm('Clear the penalty for {{ $driver->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-secondary mb-1"><i class="fas fa-undo mr-1"></i>Clear</button>
                                </form>
                            @else
                                <button class="btn btn-sm btn-warning mb-1"
                                    onclick="openPenalize({{ $driver->id }}, {{ Illuminate\Support\Js::from($driver->name) }})">
                                    <i class="fas fa-ban mr-1"></i>Penalize
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            @if(($search ?? '') !== '')
                                No {{ $status }} drivers found matching "{{ $search }}".
                            @else
                                No {{ $status }} drivers found.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($drivers->hasPages())
    <div class="card-footer">
        {{ $drivers->appends(['status' => $status, 'search' => $search])->links() }}
    </div>
    @endif
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
