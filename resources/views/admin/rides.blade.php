@extends('admin.layout')
@section('title', 'Rides')
@section('page-title', 'Rides')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Ride orders</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Ride
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Passenger</th>
                    <th>Driver</th>
                    <th>Pickup</th>
                    <th>Dropoff</th>
                    <th>Status</th>
                    <th>Fare</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rides as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->passenger?->name ?? '—' }}</td>
                    <td>{{ $r->driver?->name ?? 'Unassigned' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($r->pickup_address, 22) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($r->dropoff_address, 22) }}</td>
                    <td>
                        @php $sc = ['requested'=>'secondary','pending'=>'warning','accepted'=>'info','in_progress'=>'primary','completed'=>'success','cancelled'=>'danger']; @endphp
                        <span class="badge badge-{{ $sc[$r->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$r->status)) }}</span>
                    </td>
                    <td>{{ $r->fare ? number_format($r->fare, 0).' ៛' : '—' }}</td>
                    <td>{{ $r->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $r->id }},
                            passenger_id: {{ $r->passenger_id }},
                            driver_id: {{ $r->driver_id ?? 'null' }},
                            pickup_address: @json($r->pickup_address),
                            dropoff_address: @json($r->dropoff_address),
                            status: @json($r->status),
                            fare: '{{ $r->fare ?? '' }}',
                            service_type: @json($r->service_type ?? ''),
                            notes: @json($r->notes ?? '')
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.rides.destroy', $r) }}" class="d-inline"
                              onsubmit="return confirm('Delete ride #{{ $r->id }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No rides found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $rides->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Ride</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="rideForm" method="POST" action="{{ route('admin.rides.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Passenger <span class="text-danger">*</span></label>
                            <select name="passenger_id" id="f-passenger" class="form-control" required>
                                <option value="">— Select passenger —</option>
                                @foreach($passengers as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Driver</label>
                            <select name="driver_id" id="f-driver" class="form-control">
                                <option value="">— Unassigned —</option>
                                @foreach($drivers as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Pickup Address <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_address" id="f-pickup" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Dropoff Address <span class="text-danger">*</span></label>
                        <input type="text" name="dropoff_address" id="f-dropoff" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="f-status" class="form-control" required>
                                <option value="requested">Requested</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Fare (KHR ៛)</label>
                            <input type="number" name="fare" id="f-fare" class="form-control" min="0" step="100">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Service Type</label>
                            <input type="text" name="service_type" id="f-service" class="form-control" placeholder="standard, premium…">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="f-notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const storeUrl = '{{ route('admin.rides.store') }}';
const updateBase = '/admin/rides/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Ride';
    document.getElementById('rideForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('rideForm').reset();
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit Ride #' + d.id;
    document.getElementById('rideForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-passenger').value = d.passenger_id;
    document.getElementById('f-driver').value = d.driver_id || '';
    document.getElementById('f-pickup').value = d.pickup_address;
    document.getElementById('f-dropoff').value = d.dropoff_address;
    document.getElementById('f-status').value = d.status;
    document.getElementById('f-fare').value = d.fare;
    document.getElementById('f-service').value = d.service_type;
    document.getElementById('f-notes').value = d.notes;
    $('#formModal').modal('show');
}
</script>
@endpush
