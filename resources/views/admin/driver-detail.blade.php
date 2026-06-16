@extends('admin.layout')
@section('title', 'Driver Review — ' . $driver->name)
@section('page-title', 'Driver Review')

@section('content')
<div class="row">
    {{-- Left column: Driver info + Approval form --}}
    <div class="col-md-4">
        {{-- Profile card --}}
        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <div class="text-center">
                    @if($driver->avatar)
                        <img class="profile-user-img img-fluid img-circle" src="{{ asset('storage/' . $driver->avatar) }}" style="width:80px;height:80px;object-fit:cover;">
                    @else
                        <div class="profile-user-img img-fluid img-circle bg-secondary d-flex align-items-center justify-content-center text-white mx-auto" style="width:80px;height:80px;font-size:2rem;">
                            {{ strtoupper(substr($driver->name, 0, 1)) }}
                        </div>
                    @endif
                    <h3 class="profile-username mt-2">{{ $driver->name }}</h3>
                    <p class="text-muted">{{ $driver->driver_type ?? 'Driver' }}</p>
                </div>
                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <b>Email</b> <span class="float-right text-muted">{{ $driver->email }}</span>
                    </li>
                    <li class="list-group-item">
                        <b>Phone</b> <span class="float-right text-muted">{{ $driver->phone ?? '—' }}</span>
                    </li>
                    <li class="list-group-item">
                        <b>City</b> <span class="float-right text-muted">{{ $driver->city ?? '—' }}</span>
                    </li>
                    <li class="list-group-item">
                        <b>Service Zone</b> <span class="float-right text-muted">{{ $driver->service_zone ?? '—' }}</span>
                    </li>
                    <li class="list-group-item">
                        <b>Status</b>
                        <span class="float-right">
                            @if($driver->approval_status === 'approved')
                                <span class="badge badge-success">Approved</span>
                            @elseif($driver->approval_status === 'rejected')
                                <span class="badge badge-danger">Rejected</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </span>
                    </li>
                    @if($driver->approved_at)
                    <li class="list-group-item">
                        <b>Approved At</b> <span class="float-right text-muted">{{ $driver->approved_at->format('d M Y H:i') }}</span>
                    </li>
                    @endif
                    @if($driver->status_note)
                    <li class="list-group-item">
                        <b>Note</b> <span class="float-right text-muted" style="max-width:60%;text-align:right;">{{ $driver->status_note }}</span>
                    </li>
                    @endif
                    <li class="list-group-item">
                        <b>Registered</b> <span class="float-right text-muted">{{ $driver->created_at->format('d M Y') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Vehicle card --}}
        @if($vehicle)
        <div class="card">
            <div class="card-header"><h3 class="card-title">Vehicle</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><b>Make</b> <span class="float-right">{{ $vehicle->make }}</span></li>
                    <li class="list-group-item"><b>Model</b> <span class="float-right">{{ $vehicle->model }}</span></li>
                    <li class="list-group-item"><b>Year</b> <span class="float-right">{{ $vehicle->year }}</span></li>
                    <li class="list-group-item"><b>Plate</b> <span class="float-right">{{ $vehicle->plate_number }}</span></li>
                    <li class="list-group-item"><b>Color</b> <span class="float-right">{{ $vehicle->color ?? '—' }}</span></li>
                    <li class="list-group-item"><b>Type</b> <span class="float-right">{{ $vehicle->type ?? '—' }}</span></li>
                </ul>
            </div>
        </div>
        @endif

        {{-- Approval action form --}}
        <div class="card card-warning card-outline">
            <div class="card-header"><h3 class="card-title">Approval Decision</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.drivers.approve', $driver->id) }}">
                    @csrf
                    <div class="form-group">
                        <label>Action</label>
                        <select name="action" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="approve" {{ $driver->approval_status === 'approved' ? 'selected' : '' }}>Approve</option>
                            <option value="reject" {{ $driver->approval_status === 'rejected' ? 'selected' : '' }}>Reject</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Service Zone <small class="text-muted">(optional)</small></label>
                        <input type="text" name="service_zone" class="form-control" value="{{ $driver->service_zone }}" placeholder="e.g. Phnom Penh Central">
                    </div>
                    <div class="form-group">
                        <label>Note / Reason <small class="text-muted">(required for reject)</small></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Internal note or rejection reason...">{{ $driver->status_note }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-block">
                        <i class="fas fa-check mr-1"></i> Submit Decision
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Right column: Documents --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Documents</h3>
                @php
                    $approvedCount = $documents->where('status', 'approved')->count();
                    $totalRequired = 4;
                @endphp
                <span class="badge badge-{{ $approvedCount >= $totalRequired ? 'success' : 'warning' }} ml-2" style="font-size:.85rem;">
                    {{ $approvedCount }} / {{ $totalRequired }} approved
                </span>
            </div>
            <div class="card-body">
                @php
                    $docTypes = [
                        'id_card'              => 'National ID Card',
                        'driver_license'       => 'Driver\'s License',
                        'vehicle_registration' => 'Vehicle Registration',
                        'vehicle_insurance'    => 'Vehicle Insurance',
                        'selfie_with_id'       => 'Selfie with ID',
                        'other'                => 'Other',
                    ];
                    $required = ['id_card', 'driver_license', 'vehicle_registration', 'selfie_with_id'];
                    $docsByType = $documents->keyBy('type');
                @endphp

                @foreach($docTypes as $typeKey => $typeLabel)
                @php $doc = $docsByType->get($typeKey); @endphp
                <div class="card mb-3 {{ in_array($typeKey, $required) ? 'card-outline card-primary' : '' }}">
                    <div class="card-header py-2">
                        <h6 class="card-title mb-0">
                            {{ $typeLabel }}
                            @if(in_array($typeKey, $required))
                                <span class="badge badge-light border ml-1" style="font-size:.7rem;">Required</span>
                            @endif
                        </h6>
                        <div class="card-tools">
                            @if($doc)
                                @if($doc->status === 'approved')
                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Approved</span>
                                @elseif($doc->status === 'rejected')
                                    <span class="badge badge-danger"><i class="fas fa-times mr-1"></i>Rejected</span>
                                @else
                                    <span class="badge badge-warning">Pending Review</span>
                                @endif
                            @else
                                <span class="badge badge-secondary">Not Uploaded</span>
                            @endif
                        </div>
                    </div>
                    @if($doc)
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                @php $ext = strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION)); @endphp
                                @if(in_array($ext, ['jpg','jpeg','png','webp']))
                                    <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $doc->file_path) }}" class="img-fluid rounded" style="max-height:160px;object-fit:cover;width:100%;">
                                    </a>
                                @elseif($ext === 'pdf')
                                    <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-file-pdf mr-1 text-danger"></i> View PDF
                                    </a>
                                @else
                                    <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-file mr-1"></i> View File
                                    </a>
                                @endif
                            </div>
                            <div class="col-md-7">
                                <p class="mb-1 small text-muted">Uploaded: {{ $doc->created_at->format('d M Y H:i') }}</p>
                                @if($doc->admin_note)
                                    <p class="mb-1 small"><b>Note:</b> {{ $doc->admin_note }}</p>
                                @endif
                                @if($doc->reviewed_at && $doc->reviewer)
                                    <p class="mb-2 small text-muted">Reviewed by {{ $doc->reviewer->name }} on {{ $doc->reviewed_at->format('d M Y') }}</p>
                                @endif
                                <form method="POST" action="{{ route('admin.drivers.documents.review', [$driver->id, $doc->id]) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-xs btn-success {{ $doc->status === 'approved' ? 'disabled' : '' }}">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-xs btn-danger {{ $doc->status === 'rejected' ? 'disabled' : '' }}"
                                    onclick="rejectDoc({{ $doc->id }}, '{{ route('admin.drivers.documents.review', [$driver->id, $doc->id]) }}')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="card-body py-2 text-muted small">No file uploaded yet.</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <a href="{{ route('admin.drivers', ['status' => $driver->approval_status]) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Drivers
        </a>
    </div>
</div>

{{-- Reject doc modal --}}
<div class="modal fade" id="rejectDocModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Document</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="rejectDocForm" method="POST">
                @csrf
                <input type="hidden" name="action" value="reject">
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label>Reason / Note</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Explain why the document is rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function rejectDoc(docId, url) {
    document.getElementById('rejectDocForm').action = url;
    $('#rejectDocModal').modal('show');
}
</script>
@endpush
@endsection
