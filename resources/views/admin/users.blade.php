@extends('admin.layout')
@section('title', 'Users')
@section('page-title', 'Users')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">User list</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add User
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Driver Type</th>
                    <th>Company</th>
                    <th>Phone</th>
                    <th>Wallet</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                @php
                    $dtColor = ['owner' => 'success', 'company_staff' => 'info', 'rental' => 'warning'];
                    $dtLabel = ['owner' => 'Own Vehicle', 'company_staff' => 'Company Staff', 'rental' => 'Rental'];
                @endphp
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="badge badge-{{ $user->role === 'admin' ? 'danger' : ($user->role === 'driver' ? 'success' : 'primary') }}">
                            {{ ucfirst($user->role ?? 'passenger') }}
                        </span>
                    </td>
                    <td>
                        @if($user->role === 'driver' && $user->driver_type)
                            <span class="badge badge-{{ $dtColor[$user->driver_type] ?? 'secondary' }}">
                                {{ $dtLabel[$user->driver_type] ?? ucfirst($user->driver_type) }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $user->company_name ?? '—' }}</td>
                    <td>{{ $user->phone ?? '—' }}</td>
                    <td>{{ number_format($user->wallet_balance ?? 0, 0) }} ៛</td>
                    <td>{{ $user->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $user->id }},
                            name: @json($user->name),
                            email: @json($user->email),
                            phone: @json($user->phone ?? ''),
                            role: @json($user->role ?? 'passenger'),
                            driver_type: @json($user->driver_type ?? ''),
                            company_name: @json($user->company_name ?? ''),
                            wallet_balance: '{{ $user->wallet_balance ?? 0 }}'
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                              onsubmit="return confirm('Delete user {{ addslashes($user->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $users->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add User</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="userForm" method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="f-email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label id="pw-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="f-password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Phone</label>
                            <input type="text" name="phone" id="f-phone" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Role <span class="text-danger">*</span></label>
                            <select name="role" id="f-role" class="form-control" required onchange="toggleDriverFields()">
                                <option value="passenger">Passenger</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    {{-- Driver-only fields --}}
                    <div id="driver-fields" style="display:none;">
                        <hr class="my-2">
                        <p class="text-muted small mb-2"><i class="fas fa-car mr-1"></i> Driver Vehicle Ownership</p>
                        <div class="form-group">
                            <label>Driver Type <span class="text-danger">*</span></label>
                            <select name="driver_type" id="f-driver-type" class="form-control" onchange="toggleCompanyField()">
                                <option value="owner">Own Car / Tuk-tuk (Independent)</option>
                                <option value="company_staff">Company Staff — vehicle provided by company</option>
                                <option value="rental">Rental — rents car / tuk-tuk from company</option>
                            </select>
                        </div>
                        <div class="form-group" id="company-field" style="display:none;">
                            <label>Company Name</label>
                            <input type="text" name="company_name" id="f-company-name" class="form-control" placeholder="Company or fleet name">
                        </div>
                    </div>

                    <div class="form-group" id="wallet-group" style="display:none;">
                        <label>Wallet Balance (KHR ៛)</label>
                        <input type="number" name="wallet_balance" id="f-wallet" class="form-control" min="0" step="100">
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
const storeUrl = '{{ route('admin.users.store') }}';
const updateBase = '/admin/users/';

function toggleDriverFields() {
    const role = document.getElementById('f-role').value;
    const show = role === 'driver';
    document.getElementById('driver-fields').style.display = show ? 'block' : 'none';
    if (!show) {
        document.getElementById('f-driver-type').value = 'owner';
        document.getElementById('company-field').style.display = 'none';
    }
}

function toggleCompanyField() {
    const type = document.getElementById('f-driver-type').value;
    const needs = type === 'company_staff' || type === 'rental';
    document.getElementById('company-field').style.display = needs ? 'block' : 'none';
    if (!needs) document.getElementById('f-company-name').value = '';
}

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('userForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('pw-label').innerHTML = 'Password <span class="text-danger">*</span>';
    document.getElementById('f-password').required = true;
    document.getElementById('wallet-group').style.display = 'none';
    document.getElementById('userForm').reset();
    document.getElementById('driver-fields').style.display = 'none';
    document.getElementById('company-field').style.display = 'none';
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('pw-label').innerHTML = 'Password <small class="text-muted">(leave blank to keep)</small>';
    document.getElementById('f-password').required = false;
    document.getElementById('f-name').value = d.name;
    document.getElementById('f-email').value = d.email;
    document.getElementById('f-password').value = '';
    document.getElementById('f-phone').value = d.phone;
    document.getElementById('f-role').value = d.role;
    document.getElementById('f-wallet').value = d.wallet_balance;
    document.getElementById('wallet-group').style.display = 'block';

    // Driver fields
    const isDriver = d.role === 'driver';
    document.getElementById('driver-fields').style.display = isDriver ? 'block' : 'none';
    if (isDriver) {
        document.getElementById('f-driver-type').value = d.driver_type || 'owner';
        const needsCompany = d.driver_type === 'company_staff' || d.driver_type === 'rental';
        document.getElementById('company-field').style.display = needsCompany ? 'block' : 'none';
        document.getElementById('f-company-name').value = d.company_name || '';
    }

    $('#formModal').modal('show');
}
</script>
@endpush
