@extends('admin.layout')
@section('title', 'Deliveries')
@section('page-title', 'Deliveries')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Delivery orders</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Delivery
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Sender</th>
                    <th>Recipient</th>
                    <th>Recipient Phone</th>
                    <th>Package</th>
                    <th>Driver</th>
                    <th>Pickup</th>
                    <th>Dropoff</th>
                    <th>Status</th>
                    <th>Total Fee</th>
                    <th>Scheduled</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deliveries as $d)
                @php
                    $pc = ['small' => 'success', 'medium' => 'warning', 'large' => 'danger'];
                    $sc = ['pending' => 'warning', 'accepted' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
                @endphp
                <tr>
                    <td>{{ $d->id }}</td>
                    <td>
                        <div>{{ $d->sender_name ?? $d->sender?->name ?? '—' }}</div>
                        <small class="text-muted">{{ $d->sender?->email }}</small>
                    </td>
                    <td>{{ $d->recipient_name ?? '—' }}</td>
                    <td>{{ $d->recipient_phone ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $pc[$d->package_size] ?? 'secondary' }}">
                            {{ ucfirst($d->package_size ?? '—') }}
                        </span>
                    </td>
                    <td>{{ $d->driver?->name ?? 'Unassigned' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($d->pickup_address, 20) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($d->dropoff_address, 20) }}</td>
                    <td>
                        <span class="badge badge-{{ $sc[$d->status] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_', ' ', $d->status)) }}
                        </span>
                    </td>
                    <td>{{ $d->fee ? '$'.number_format($d->fee, 2) : '—' }}</td>
                    <td>{{ $d->scheduled_at ? $d->scheduled_at->format('Y-m-d H:i') : '—' }}</td>
                    <td>{{ $d->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1"
                            data-delivery="{{ e(json_encode([
                                'id'              => $d->id,
                                'sender_id'       => $d->sender_id,
                                'sender_name'     => $d->sender_name ?? '',
                                'recipient_name'  => $d->recipient_name ?? '',
                                'recipient_phone' => $d->recipient_phone ?? '',
                                'package_size'    => $d->package_size ?? 'small',
                                'driver_id'       => $d->driver_id,
                                'pickup_address'  => $d->pickup_address,
                                'dropoff_address' => $d->dropoff_address,
                                'status'          => $d->status,
                                'fee'             => $d->fee ?? '',
                                'scheduled_at'    => $d->scheduled_at ? $d->scheduled_at->format('Y-m-d\TH:i') : '',
                                'notes'           => $d->notes ?? '',
                            ])) }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.deliveries.destroy', $d) }}" class="d-inline"
                              onsubmit="return confirm('Delete delivery #{{ $d->id }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="13" class="text-center text-muted py-4">No deliveries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $deliveries->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Delivery</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="deliveryForm" method="POST" action="{{ route('admin.deliveries.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">

                    {{-- Sender --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Sender Account <span class="text-danger">*</span></label>
                            <select name="sender_id" id="f-sender" class="form-control" required>
                                <option value="">— Select account —</option>
                                @foreach($senders as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Sender Name <span class="text-danger">*</span></label>
                            <input type="text" name="sender_name" id="f-sender-name" class="form-control" placeholder="Full name of sender" required>
                        </div>
                    </div>

                    {{-- Recipient --}}
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_name" id="f-recipient-name" class="form-control" placeholder="Full name of recipient" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Recipient Phone <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_phone" id="f-recipient-phone" class="form-control" placeholder="012-345-6789" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Package Size <span class="text-danger">*</span></label>
                            <select name="package_size" id="f-package-size" class="form-control" required>
                                <option value="small">Small — fits in a backpack</option>
                                <option value="medium">Medium — fits in a car boot</option>
                                <option value="large">Large — requires a van</option>
                            </select>
                        </div>
                    </div>

                    {{-- Driver --}}
                    <div class="form-group">
                        <label>Driver</label>
                        <select name="driver_id" id="f-driver" class="form-control">
                            <option value="">— Unassigned —</option>
                            @foreach($drivers as $dr)
                                <option value="{{ $dr->id }}">{{ $dr->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Addresses --}}
                    <div class="form-group">
                        <label>Pickup Address <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_address" id="f-pickup" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Dropoff Address <span class="text-danger">*</span></label>
                        <input type="text" name="dropoff_address" id="f-dropoff" class="form-control" required>
                    </div>

                    {{-- Status, Fee & Schedule --}}
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="f-status" class="form-control" required>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Total Fee ($)</label>
                            <input type="number" name="fee" id="f-fee" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Scheduled Date</label>
                            <input type="datetime-local" name="scheduled_at" id="f-scheduled-at" class="form-control">
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="f-notes" class="form-control" rows="2" placeholder="Special instructions…"></textarea>
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
const storeUrl = "{{ route('admin.deliveries.store') }}";
const updateBase = '/admin/deliveries/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Delivery';
    document.getElementById('deliveryForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('deliveryForm').reset();
    $('#formModal').modal('show');
}

function openEdit(btn) {
    const d = JSON.parse(btn.getAttribute('data-delivery'));
    document.getElementById('modalTitle').textContent   = 'Edit Delivery #' + d.id;
    document.getElementById('deliveryForm').action      = updateBase + d.id;
    document.getElementById('formMethod').value         = 'PUT';
    document.getElementById('f-sender').value           = d.sender_id;
    document.getElementById('f-sender-name').value      = d.sender_name;
    document.getElementById('f-recipient-name').value   = d.recipient_name;
    document.getElementById('f-recipient-phone').value  = d.recipient_phone;
    document.getElementById('f-package-size').value     = d.package_size;
    document.getElementById('f-driver').value           = d.driver_id || '';
    document.getElementById('f-pickup').value           = d.pickup_address;
    document.getElementById('f-dropoff').value          = d.dropoff_address;
    document.getElementById('f-status').value           = d.status;
    document.getElementById('f-fee').value              = d.fee;
    document.getElementById('f-scheduled-at').value     = d.scheduled_at;
    document.getElementById('f-notes').value            = d.notes;
    $('#formModal').modal('show');
}
</script>
@endpush
