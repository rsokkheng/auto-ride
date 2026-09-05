@extends('admin.layout')
@section('title', 'Incident #' . $incident->id)
@section('page-title', 'Safety Incident #' . $incident->id)

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<a href="{{ route('admin.safety') }}" class="btn btn-sm btn-secondary mb-3">
    <i class="fas fa-arrow-left mr-1"></i> Back to Safety Incidents
</a>

@php
    $tc = ['accident'=>'danger','harassment'=>'warning','theft'=>'dark','other'=>'secondary'];
    $sc = ['reported'=>'warning','investigating'=>'info','resolved'=>'success','closed'=>'secondary'];
    $roleColor = ['admin'=>'danger','driver'=>'primary','passenger'=>'success','partner'=>'info'];
@endphp

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <span class="badge badge-{{ $tc[$incident->incident_type] ?? 'secondary' }}">{{ ucfirst($incident->incident_type) }}</span>
                    Incident #{{ $incident->id }}
                </h3>
                <span class="badge badge-{{ $sc[$incident->status] ?? 'secondary' }}">{{ ucfirst($incident->status) }}</span>
            </div>
            <div class="card-body">
                <h6 class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;">Description</h6>
                <p style="white-space:pre-wrap;">{{ $incident->description }}</p>

                @if($incident->resolution)
                <hr>
                <h6 class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;">Resolution Notes</h6>
                <p style="white-space:pre-wrap;">{{ $incident->resolution }}</p>
                @endif

                <hr>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Reported At</dt>
                    <dd class="col-sm-8">{{ ($incident->reported_at ?? $incident->created_at)->format('d M Y, g:i A') }}</dd>

                    <dt class="col-sm-4">Last Updated</dt>
                    <dd class="col-sm-8">{{ $incident->updated_at->format('d M Y, g:i A') }}</dd>
                </dl>
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('admin.safety.resolution', $incident) }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                @foreach(['reported','investigating','resolved','closed'] as $s)
                                    <option value="{{ $s }}" {{ $incident->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-8">
                            <label>Resolution Notes</label>
                            <input type="text" name="resolution" class="form-control" value="{{ $incident->resolution }}"
                                   placeholder="What was done about this incident?">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        {{-- Reporter --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-user mr-1"></i>Reported By</h3></div>
            <div class="card-body">
                @if($incident->user)
                    <div class="font-weight-bold">{{ $incident->user->name }}</div>
                    <span class="badge badge-{{ $roleColor[$incident->user->role] ?? 'secondary' }} mb-2">{{ ucfirst($incident->user->role) }}</span>
                    <div class="text-muted small"><i class="fas fa-phone mr-1"></i>{{ $incident->user->phone ?? '—' }}</div>
                    <div class="text-muted small"><i class="fas fa-envelope mr-1"></i>{{ $incident->user->email }}</div>
                @else
                    <span class="text-muted">User account no longer exists.</span>
                @endif
            </div>
        </div>

        {{-- Related ride: both parties --}}
        @if($incident->ride)
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-route mr-1"></i>Related Ride</h3>
                <a href="{{ route('admin.rides.show', $incident->ride) }}" class="btn btn-xs btn-outline-primary">View Ride #{{ $incident->ride->id }}</a>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge badge-success mb-1">Passenger</span>
                    <div class="font-weight-bold">{{ $incident->ride->passenger->name ?? '—' }}</div>
                    <div class="text-muted small">{{ $incident->ride->passenger->phone ?? '—' }}</div>
                </div>
                <div>
                    <span class="badge badge-primary mb-1">Driver</span>
                    <div class="font-weight-bold">{{ $incident->ride->driver->name ?? 'Unassigned' }}</div>
                    <div class="text-muted small">{{ $incident->ride->driver->phone ?? '—' }}</div>
                </div>
                <hr>
                <div class="text-muted small">
                    <div>{{ $incident->ride->pickup_address }}</div>
                    <div><i class="fas fa-arrow-down mx-1" style="font-size:.65rem;"></i></div>
                    <div>{{ $incident->ride->dropoff_address ?? '—' }}</div>
                </div>
            </div>
        </div>
        @endif

        {{-- Related delivery: both parties --}}
        @if($incident->delivery)
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-box mr-1"></i>Related Delivery</h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge badge-success mb-1">Sender</span>
                    <div class="font-weight-bold">{{ $incident->delivery->sender_name ?? '—' }}</div>
                    <div class="text-muted small">{{ $incident->delivery->sender_phone ?? '—' }}</div>
                </div>
                <div class="mb-3">
                    <span class="badge badge-warning mb-1">Recipient</span>
                    <div class="font-weight-bold">{{ $incident->delivery->recipient_name ?? '—' }}</div>
                    <div class="text-muted small">{{ $incident->delivery->recipient_phone ?? '—' }}</div>
                </div>
                <div>
                    <span class="badge badge-primary mb-1">Driver</span>
                    <div class="font-weight-bold">{{ $incident->delivery->driver->name ?? 'Unassigned' }}</div>
                    <div class="text-muted small">{{ $incident->delivery->driver->phone ?? '—' }}</div>
                </div>
            </div>
        </div>
        @endif

        @if(! $incident->ride && ! $incident->delivery)
        <div class="card">
            <div class="card-body text-muted text-center py-4">
                <i class="fas fa-unlink mb-2 d-block" style="font-size:1.5rem;"></i>
                Not linked to a specific ride or delivery.
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
