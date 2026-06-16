@extends('admin.layout')
@section('title', 'Driver Approvals')
@section('page-title', 'Driver Approvals')

@section('content')
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.drivers', ['status' => 'pending']) }}">
                    Pending
                    @if($counts['pending'] > 0)
                        <span class="badge badge-danger ml-1">{{ $counts['pending'] }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('admin.drivers', ['status' => 'approved']) }}">
                    Approved
                    @if($counts['approved'] > 0)
                        <span class="badge badge-success ml-1">{{ $counts['approved'] }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('admin.drivers', ['status' => 'rejected']) }}">
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
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drivers as $driver)
                    <tr>
                        <td>{{ $driver->id }}</td>
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
                        <td>{{ $driver->created_at->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('admin.drivers.show', $driver->id) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye mr-1"></i> Review
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No {{ $status }} drivers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($drivers->hasPages())
    <div class="card-footer">
        {{ $drivers->appends(['status' => $status])->links() }}
    </div>
    @endif
</div>
@endsection
